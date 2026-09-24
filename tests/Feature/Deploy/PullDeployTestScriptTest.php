<?php

/**
 * The staging pull script's post-deploy gates.
 *
 * S2.0 added a read-only `students:verify-unification` gate after the
 * morph-map one. S1 Deploy 3 archived `registration_students` and retired
 * that command (STATUS §5gh), so the gate went with it; the morph-map gate
 * stays.
 */
function pullDeployTestScript(): string
{
    return file_get_contents(base_path('scripts/pull-deploy-test.sh'));
}

it('still gates the deploy on morph-map:verify', function () {
    $script = pullDeployTestScript();

    expect($script)->toContain('php artisan morph-map:verify 2>&1')
        ->and($script)->toContain('======== MORPH-MAP GATE FAILED ========');
});

it('no longer calls the retired unification verify', function () {
    expect(pullDeployTestScript())->not->toContain('php artisan students:verify-unification');
});

it('is valid bash', function () {
    $path = base_path('scripts/pull-deploy-test.sh');
    exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $exit);

    expect($exit)->toBe(0, implode("\n", $output));
});
