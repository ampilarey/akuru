<?php

/**
 * LIBRARY_PLAN §15.4, which the plan writes in capitals and CLAUDE.md rule 12
 * repeats:
 *
 *   > **CRITICAL RULE:** discount codes can NEVER be used to buy gift cards
 *   > (abuse: 50% code buys MVR 1000 card for 500, spends 1000). Gift cards
 *   > purchased only via real payment (BML) unless admin-issued.
 *
 * The arithmetic is the whole point: a discount reduces a price, and a gift
 * card **is money**, so discounting one mints currency at a loss. It is the one
 * rule in the commerce plan written as an attack rather than a preference.
 *
 * **Today it holds for a reason that will expire.** §15.3's purchase flow —
 * "select amount → recipient details → message → BML → webhook → generate code"
 * — does not exist. Gift cards are only ever created by
 * `AdminCommerceController::issueGiftCard`, and no payment in the system
 * carries `payable_type = 'gift_card'`. So a discount cannot be applied to a
 * gift card purchase because there is no gift card purchase.
 *
 * That is not enforcement, it is an accident of sequencing, and it ends the day
 * somebody builds §15.3 — which the plan asks for, in a file that does not
 * mention §15.4 anywhere near the code that would need it.
 *
 * So this pins the call sites instead of inventing a runtime branch for a flow
 * that does not exist. `ResolveDiscountAction` takes an amount and cannot see
 * what is being bought, so **only its callers can honour §15.4**. When a third
 * caller appears, this test fails and whoever wrote it has to say, here, what
 * they are discounting. If the answer is a gift card, the answer is no.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('lets only the two non-gift-card checkouts resolve a discount', function () {
    $allowed = [
        // Course fees. A course is a service, not stored value.
        'app/Domains/Courses/Actions/StartCourseCheckoutAction.php',
        // A book, an article or a research item. Same.
        'app/Domains/Library/Actions/StartLibraryCheckoutAction.php',
    ];

    $callers = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        // The action itself, obviously.
        if (str_ends_with($path, 'Actions/ResolveDiscountAction.php')) {
            continue;
        }

        $source = stripPhpComments((string) file_get_contents($file->getPathname()));

        if (str_contains($source, 'ResolveDiscountAction')) {
            $callers[] = $path;
        }
    }

    sort($callers);

    expect($callers)->toBe($allowed,
        'LIBRARY_PLAN §15.4 / CLAUDE.md rule 12: "discount codes can NEVER be used to '
        ."buy gift cards\".\n\n`ResolveDiscountAction` receives an amount and cannot see what "
        .'is being bought, so only the caller can honour that rule. Something new resolves a '
        ."discount:\n  "
        .implode("\n  ", array_diff($callers, $allowed))
        ."\n\nIf it is buying a course or a library item, add it to the list in this test. "
        .'If it is buying a gift card, or anything else that stores value, it must not take a '
        .'discount at all — a discounted gift card mints money at a loss, which is the abuse '
        .'§15.4 is written about.'
    );
});

it('has no gift card purchase path yet, which is why the rule above has held', function () {
    // Recorded as a fact rather than a hope. `payable_type = 'gift_card'` does
    // not exist in the codebase: gift cards are admin-issued only, and §15.3's
    // BML purchase flow is unbuilt. When that changes this test fails, which is
    // the moment to read §15.4 again.
    //
    // `config/morph-map.php` DOES alias `gift_card`, and correctly — ADR-005
    // requires an alias for every polymorphic model whether or not anything
    // points at it yet. An alias is not a purchase path.
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = stripPhpComments((string) file_get_contents($file->getPathname()));

        if (preg_match('/[\'"]payable_type[\'"]\s*=>\s*[\'"]gift_card[\'"]/', $source)
            || preg_match('/payable_type[\'"]?\s*,\s*[\'"]gift_card[\'"]/', $source)) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBeEmpty(
        "A payment now treats a gift card as its payable:\n  "
        .implode("\n  ", $offenders)
        ."\n\nThat is §15.3's purchase flow arriving. Before it ships, §15.4 needs real "
        .'enforcement: a gift card is bought with money, never with a discount code.'
    );
});
