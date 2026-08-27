<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Contracts\ImageProcessorInterface;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class WebPImageService implements ImageProcessorInterface
{
    /** Supported source formats for WebP conversion */
    protected array $supportedFormats = ['jpg', 'jpeg', 'png'];

    /**
     * Get the WebP path for an image. Generates WebP if it doesn't exist.
     * Returns null if conversion fails or format is not supported.
     */
    public function getWebPPath(string $storagePath): ?string
    {
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->supportedFormats)) {
            return null;
        }

        $webpPath = preg_replace('/\.'.preg_quote($extension, '/').'$/i', '.webp', $storagePath);

        if (Storage::disk('public')->exists($webpPath)) {
            return $webpPath;
        }

        return $this->generateWebP($storagePath, $webpPath);
    }

    /**
     * Generate WebP version of an image.
     */
    protected function generateWebP(string $sourcePath, string $webpPath): ?string
    {
        try {
            $fullPath = Storage::disk('public')->path($sourcePath);

            if (! file_exists($fullPath)) {
                return null;
            }

            $manager = ImageManager::gd();
            $image = $manager->read($fullPath);
            $encoded = $image->toWebp(85);

            $webpFullPath = Storage::disk('public')->path($webpPath);
            $webpDir = dirname($webpFullPath);

            if (! is_dir($webpDir)) {
                mkdir($webpDir, 0755, true);
            }

            $encoded->save($webpFullPath);

            return $webpPath;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Check if a path is eligible for WebP conversion.
     */
    public function isConvertible(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $this->supportedFormats);
    }

    /**
     * @param  array{background?: string, lines?: list<array{text: string, font?: string, size?: int, color?: string, x?: int, y?: int, align?: string, valign?: string, wrap?: int}>}  $spec
     */
    public function renderSquarePng(int $size, array $spec): string
    {
        $manager = ImageManager::gd();
        $image = $manager->create($size, $size);
        $image->fill($spec['background'] ?? '#3D1219');

        $fonts = config('media.card_fonts', []);

        foreach ($spec['lines'] ?? [] as $line) {
            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $fontKey = (string) ($line['font'] ?? 'latin');
            $path = is_string($fonts[$fontKey] ?? null) ? $fonts[$fontKey] : $fontKey;
            if (! is_file($path)) {
                $path = (string) ($fonts['latin'] ?? '');
            }
            if (! is_file($path)) {
                continue;
            }

            try {
                $image->text($text, (int) ($line['x'] ?? (int) ($size / 2)), (int) ($line['y'] ?? 0), function ($font) use ($line, $path): void {
                    $font->filename($path);
                    $font->size((int) ($line['size'] ?? 32));
                    $font->color((string) ($line['color'] ?? '#F5E6C8'));
                    $font->align((string) ($line['align'] ?? 'center'));
                    $font->valign((string) ($line['valign'] ?? 'top'));
                    $wrap = (int) ($line['wrap'] ?? 0);
                    if ($wrap > 0) {
                        $font->wrap($wrap);
                    }
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return (string) $image->toPng();
    }
}
