<?php

namespace App\Support\Services;

use App\Support\Contracts\PdfRasterizerInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * NAME_TAGS_PLAN §5.6 (slice NT5): Ghostscript as a command, no SDK (rule
 * 4), with the converters' tmp-dir discipline — the PDF is written to disk,
 * read in safe mode, and everything is removed afterwards.
 */
class GhostscriptPdfRasterizer implements PdfRasterizerInterface
{
    public function __construct(private readonly ?string $gsPath, private readonly int $timeoutSeconds = 60) {}

    public function enabled(): bool
    {
        return $this->gsPath !== null && $this->gsPath !== '' && is_executable($this->gsPath);
    }

    public function pages(string $pdf): int
    {
        $out = $this->run($pdf, fn (string $in) => ['-q', '-dNODISPLAY', '-dSAFER', '--permit-file-read='.$in, '-dNOPAUSE', '-dBATCH', '-c', '('.$in.') (r) file runpdfbegin pdfpagecount = quit']);

        return max(0, (int) trim($out));
    }

    public function toPng(string $pdf, int $page, int $dpi = 96): string
    {
        $dir = $this->dir();
        $png = $dir.'/'.Str::random(24).'.png';
        try {
            $this->run($pdf, fn (string $in) => ['-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=png16m', '-r'.max(24, $dpi), '-dFirstPage='.$page, '-dLastPage='.$page, '-sOutputFile='.$png, $in]);
            $bytes = is_file($png) ? (string) file_get_contents($png) : '';
            if ($bytes === '') {
                throw new RuntimeException('Ghostscript wrote no picture.');
            }

            return $bytes;
        } finally {
            @unlink($png);
        }
    }

    /** @param  \Closure(string): list<string>  $args */
    private function run(string $pdf, \Closure $args): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('No Ghostscript is configured on this host (DOCUMENTS_GS_PATH).');
        }
        $in = $this->dir().'/'.Str::random(24).'.pdf';
        file_put_contents($in, $pdf);
        try {
            $process = new Process([(string) $this->gsPath, ...$args($in)]);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Ghostscript failed: '.mb_substr($process->getErrorOutput(), 0, 300));
            }

            return $process->getOutput();
        } finally {
            (new Filesystem)->delete($in);
        }
    }

    private function dir(): string
    {
        $dir = storage_path('app/tmp/pdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
