<?php

use Tests\Architecture\Support\ViolationScanner;

/**
 * PHASE_0_CHECKLIST §0.5 rules 1, 2, 4, 5 — baseline enforcement.
 * Grandfathered legacy violations are enumerated in tests/Architecture/Baselines/.
 * The baseline may only shrink when code is fixed; new violators fail CI.
 */
function assertMatchesBaseline(string $ruleLabel, array $current, array $baseline): void
{
    $diff = ViolationScanner::diffBaseline($current, $baseline);

    expect($diff['added'])->toBeEmpty(
        "{$ruleLabel}: new violators detected (baseline may only shrink):\n".implode("\n", $diff['added'])
    );

    expect($diff['removed'])->toBeEmpty(
        "{$ruleLabel}: fixed violators — remove from baseline:\n".implode("\n", $diff['removed'])
    );
}

it('rule 1 cross-domain Model imports match baseline', function () {
    $baseline = require __DIR__.'/Baselines/cross_domain_models.php';
    assertMatchesBaseline('Rule 1', ViolationScanner::crossDomainModelViolators(), $baseline);
});

it('rule 2 cross-domain references outside Contracts/DTOs/Events/Actions match baseline', function () {
    $baseline = require __DIR__.'/Baselines/cross_domain_non_contract.php';
    assertMatchesBaseline('Rule 2', ViolationScanner::crossDomainNonContractViolators(), $baseline);
});

it('rule 4 domain controllers using DB facade match baseline', function () {
    $baseline = require __DIR__.'/Baselines/controllers_using_db_facade.php';
    assertMatchesBaseline('Rule 4', ViolationScanner::controllersUsingDbFacade(), $baseline);
});

it('rule 5 Hifz referenced outside Hifz domain matches baseline', function () {
    $baseline = require __DIR__.'/Baselines/hifz_referenced_outside_hifz.php';
    assertMatchesBaseline('Rule 5', ViolationScanner::hifzReferencedOutsideHifz(), $baseline);
});

it('rule 6 commerce wallet tables are untouched outside Commerce', function () {
    // Activated in L4: the money tables exist now. Only the Commerce domain
    // may touch them — other domains go through Commerce Actions (rule 12:
    // append-only ledgers, one owner).
    $tables = ['wallets', 'wallet_transactions', 'gift_cards', 'gift_card_transactions'];
    $violations = [];
    $root = base_path('app');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace(base_path().'/', '', $file->getPathname());
        if (str_starts_with($path, 'app/Domains/Commerce/')) {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (str_contains($contents, 'App\\Domains\\Commerce\\Models\\')) {
            $violations[] = $path.' -> Commerce\\Models';
        }
        foreach ($tables as $table) {
            if (preg_match('/DB::table\(\s*[\'"]'.$table.'[\'"]/', $contents)) {
                $violations[] = $path.' -> DB::table('.$table.')';
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Only Commerce touches the money tables (rule 6/12):\n".implode("\n", $violations)
    );
});
