<?php

use Illuminate\Support\Facades\Route;

/**
 * The EduPage parity plan does not claim shipped work is missing.
 *
 * CLAUDE.md is blunt about this document: *"verify every row against the code
 * before starting a slice — it has been wrong 16 times across two audits,
 * always recording as missing work that already shipped."*
 *
 * It was wrong a seventeenth time. Both `docs/EDUPAGE_FEATURES_PLAN.md` and
 * `STATUS.md` carried **"the family-facing core is now just E1 + E2 + E3"**
 * long after all three shipped — contradicted by the plan's **own** corrections
 * table a few hundred lines above, which already says E1's family half ships
 * (`ComposePortalHomeAction`), E2 ships (`Message`, threads, participants,
 * polls) and E3 ships (the homework reader).
 *
 * The document even warns against exactly this: *"Do not quote a total from
 * this document without re-checking the slice against the codebase first. That
 * is exactly how the first version went wrong."*
 *
 * ## What this test does about it
 *
 * Two things a reader cannot do by eye:
 *
 *  1. Asserts the routes that **prove** E1, E2 and E3 exist, so the claim
 *     cannot become true again by accident — if somebody deletes the portal
 *     home, this fails rather than the estimate quietly becoming right.
 *  2. Asserts neither document says those three are outstanding.
 *
 * It cannot stop a differently-worded wrong claim. Nothing can. What it stops
 * is *this* claim, which has now survived two audits that were looking for it.
 */
it('has the routes that prove the family-facing core ships', function () {
    $proof = [
        // E1 — tiles, both halves. The corrections table says only the teacher
        // half was ever missing, and it was built as E1b.
        'portal.home' => 'E1 family home',
        'portal.teacher' => 'E1 teacher home',

        // E2 — message threads, not merely a notifications list.
        'portal.messages' => 'E2 thread list',
        'portal.messages.show' => 'E2 one thread',
        'portal.messages.reply' => 'E2 replying',

        // E3 — the homework reader and its tick.
        'portal.homework' => 'E3 homework list',
        'portal.homework.tick' => 'E3 marking one done',
    ];

    $missing = [];

    foreach ($proof as $name => $what) {
        if (! Route::has($name)) {
            $missing[] = $name.'  ('.$what.')';
        }
    }

    expect($missing)->toBeEmpty(
        "These routes are gone, so the parity plan's E1/E2/E3 claim may have become true:\n  "
        .implode("\n  ", $missing)
        ."\n\nIf a feature was genuinely removed, say so in docs/EDUPAGE_FEATURES_PLAN.md "
        .'and here together — the point of this test is that the document and the code '
        .'cannot drift apart in silence.'
    );
});

it('does not record E1, E2 or E3 as still to build', function () {
    $stale = 'family-facing core is now just E1 + E2 + E3';
    $staleStatus = 'The family-facing core that remains is E1 + E2 + E3';

    expect(file_get_contents(base_path('docs/EDUPAGE_FEATURES_PLAN.md')))
        ->not->toContain($stale);

    expect(file_get_contents(base_path('STATUS.md')))
        ->not->toContain($staleStatus);
});
