<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * C17 slice R2 (STATUS §5od): the ID-card scan's parser is JavaScript, tested
 * with node's own runner in `tests/js/id-scan.test.mjs`. CI runs Pest, not
 * node, so this runs those tests under Pest — a parser change that breaks a
 * reading fails the build like any other test.
 *
 * And the files the scan loads in the browser are self-hosted: the worker,
 * the three engine builds a browser may pick, and the English data. A
 * missing one is a scan that never finishes on that kind of phone.
 */
it('reads ID cards and passports the way its tests say', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed here.');
    }

    $process = new Process([$node, '--test', base_path('tests/js/id-scan.test.mjs')], base_path());
    $process->setTimeout(60)->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
});

it('serves every file the in-browser reader asks for from this site', function () {
    $base = public_path('ocr/tesseract/'.config('registration.ocr_version'));

    foreach ([
        'worker.min.js',
        'core/tesseract-core-lstm.wasm.js',
        'core/tesseract-core-simd-lstm.wasm.js',
        'core/tesseract-core-relaxedsimd-lstm.wasm.js',
        'lang/eng.traineddata.gz',
    ] as $file) {
        expect(is_file($base.'/'.$file))->toBeTrue("missing {$file}");
    }
});
