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

it('rule 6 commerce wallet tables are untouched outside Commerce')->todo(
    'Activates in L4 (Commerce track). No wallet/gift-card tables exist yet.'
);
