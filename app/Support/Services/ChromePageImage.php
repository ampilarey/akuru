<?php

namespace App\Support\Services;

use App\Support\Contracts\PageImageInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * NAME_TAGS_PLAN §5.2 (slice NT0): the proof image, taken by the same headless
 * Chrome that prints the PDF (`ChromePdfConverter`), with the same tmp-dir
 * discipline — the document is written to disk, opened as a file, its
 * scripts taken out first, and everything removed afterwards.
 */
class ChromePageImage implements PageImageInterface
{
    public function __construct(private readonly ?string $chromePath, private readonly int $timeoutSeconds = 60) {}

    public function enabled(): bool
    {
        return $this->chromePath !== null && $this->chromePath !== '' && is_executable($this->chromePath);
    }

    public function fromHtml(string $html, int $widthPx, int $heightPx): string
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
        $out = $dir.'/'.$name.'.png';
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
                '--hide-scrollbars',
                '--force-device-scale-factor=1',
                '--window-size='.max(1, $widthPx).','.max(1, $heightPx),
                '--user-data-dir='.$dir.'/profile-'.$name,
                '--screenshot='.$out,
                'file://'.$in,
            ]);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            $bytes = is_file($out) ? (string) file_get_contents($out) : '';
            if (! str_starts_with($bytes, "\x89PNG")) {
                throw new RuntimeException('Chrome did not produce an image: '.Str::limit(trim($process->getErrorOutput()), 300));
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
