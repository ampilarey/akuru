<?php

/**
 * S2.0: the staging pull script must gate student unification read-only.
 * Never pass --backfill (that writes mappings). Same warn/fail pattern as morph-map.
 */
function pullDeployTestScript(): string
{
    return file_get_contents(base_path('scripts/pull-deploy-test.sh'));
}

it('runs students:verify-unification after morph-map:verify', function () {
    $script = pullDeployTestScript();

    expect($script)->toContain('morph-map:verify')
        ->and($script)->toContain('students:verify-unification')
        ->and(strpos($script, 'morph-map:verify'))->toBeLessThan(strpos($script, 'students:verify-unification'));
});

it('invokes students:verify-unification without --backfill', function () {
    $script = pullDeployTestScript();

    expect($script)->toContain('php artisan students:verify-unification 2>&1')
        ->and($script)->not->toContain('students:verify-unification --');
});

it('fails the deploy on a nonzero unification verify', function () {
    $script = pullDeployTestScript();

    expect($script)->toContain('======== STUDENT-UNIFICATION GATE FAILED ========')
        ->and($script)->toContain('WARN: students:verify-unification not available — skipping gate (older commit)');
});

it('is valid bash', function () {
    $path = base_path('scripts/pull-deploy-test.sh');
    exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $exit);

    expect($exit)->toBe(0, implode("\n", $output));
});
