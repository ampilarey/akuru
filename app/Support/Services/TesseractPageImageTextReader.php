<?php

namespace App\Support\Services;

use App\Domains\Library\Contracts\PageImageTextReader;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * BACKLOG B1: Tesseract as a command, no SDK (rule 4), with the rasteriser's
 * tmp-dir discipline — the picture is written to disk, read, and removed.
 * The languages are Tesseract's own codes joined by `+` (`eng+div+ara`);
 * each needs its trained data installed beside the binary.
 */
class TesseractPageImageTextReader implements PageImageTextReader
{
    public function __construct(
        private readonly ?string $tesseractPath,
        private readonly string $languages = 'eng',
        private readonly int $timeoutSeconds = 40,
    ) {}

    public function enabled(): bool
    {
        return $this->tesseractPath !== null && $this->tesseractPath !== '' && is_executable($this->tesseractPath);
    }

    public function text(string $png): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('No Tesseract is configured on this host (LIBRARY_OCR_TESSERACT_PATH).');
        }
        $dir = storage_path('app/tmp/ocr');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $in = $dir.'/'.Str::random(24).'.png';
        file_put_contents($in, $png);
        try {
            // `stdout` as the output base prints the text instead of writing a file.
            $process = new Process([(string) $this->tesseractPath, $in, 'stdout', '-l', $this->languages]);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Tesseract failed: '.mb_substr($process->getErrorOutput(), 0, 300));
            }

            return trim($process->getOutput());
        } finally {
            @unlink($in);
        }
    }
}
