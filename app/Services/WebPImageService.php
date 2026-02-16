<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class WebPImageService
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

        $webpPath = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '.webp', $storagePath);

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
}
