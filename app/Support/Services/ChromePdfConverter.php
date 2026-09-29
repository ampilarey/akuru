<?php

namespace App\Support\Services;

use App\Support\Contracts\PdfConverterInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * ADR-012's PDF path (STATUS §5lr): the same Blink engine that lays out the
 * HTML in a browser prints it, so Thaana, Arabic and the page's own
 * direction come out exactly as on screen. The document is the app's own
 * template; its scripts (a print button, say) are taken out before it is
 * printed, since nothing in a printed page needs to run. (Chrome's own
 * script switch also stops it printing, so they are removed instead.)
 */
class ChromePdfConverter implements PdfConverterInterface
{
    public function __construct(private readonly ?string $chromePath, private readonly int $timeoutSeconds = 60) {}

    public function enabled(): bool
    {
        return $this->chromePath !== null && $this->chromePath !== '' && is_executable($this->chromePath);
    }

    public function fromHtml(string $html): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('No Chrome is configured on this host (DOCUMENTS_CHROME_PATH).');
        }

        $dir = storage_path('app/tmp/pdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = Str::random(24);
        $in = $dir.'/'.$name.'.html';
        $out = $dir.'/'.$name.'.pdf';
        file_put_contents($in, (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html));

        try {
            $process = new Process([
                (string) $this->chromePath,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--no-pdf-header-footer',
                '--user-data-dir='.$dir.'/profile-'.$name,
                '--print-to-pdf='.$out,
                'file://'.$in,
            ]);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            $bytes = is_file($out) ? (string) file_get_contents($out) : '';
            if (! str_starts_with($bytes, '%PDF')) {
                throw new RuntimeException('Chrome did not produce a PDF: '.Str::limit(trim($process->getErrorOutput()), 300));
            }

            return $bytes;
        } finally {
            @unlink($in);
            @unlink($out);
            if (is_dir($dir.'/profile-'.$name)) {
                (new Filesystem)->deleteDirectory($dir.'/profile-'.$name);
            }
        }
    }
}
