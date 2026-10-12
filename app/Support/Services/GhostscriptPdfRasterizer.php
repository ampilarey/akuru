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
    private ?string $version = null;

    public function __construct(private readonly ?string $gsPath, private readonly int $timeoutSeconds = 60) {}

    public function enabled(): bool
    {
        return $this->gsPath !== null && $this->gsPath !== '' && is_executable($this->gsPath);
    }

    public function pages(string $pdf): int
    {
        $version = $this->version();
        $out = $this->run($pdf, fn (string $in) => ['-q', '-dNODISPLAY', '-dSAFER', ...self::readPermission($version, $in), '-dNOPAUSE', '-dBATCH', '-c', '('.$in.') (r) file runpdfbegin pdfpagecount = quit']);

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

    /**
     * `--permit-file-read` arrived with Ghostscript 9.50, when safe mode began
     * to refuse reading files; an older one (a shared host had 9.27,
     * 2026-10-12) does not know the switch and fails on it, and its safe mode
     * never stopped a read. So the switch is passed only where it exists.
     *
     * @return list<string>
     */
    public static function readPermission(string $version, string $in): array
    {
        return version_compare($version, '9.50', '>=') ? ['--permit-file-read='.$in] : [];
    }

    /** The host's Ghostscript version, asked once (`gs --version`); a newer one is assumed if it will not say. */
    private function version(): string
    {
        if ($this->version === null) {
            if (! $this->enabled()) {
                return '99';
            }
            $process = new Process([(string) $this->gsPath, '--version']);
            $process->setTimeout(10);
            $process->run();
            $said = trim($process->getOutput());
            $this->version = preg_match('/^\d+(\.\d+)+$/', $said) === 1 ? $said : '99';
        }

        return $this->version;
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
