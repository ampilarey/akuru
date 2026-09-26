<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

/**
 * One public image id → the URL of a copy no wider than `$width`, made on
 * first ask and kept (BOOKSHOP_PLAN B1b: a shop listing shows dozens of
 * product photos, and the originals are phone photos of several megabytes).
 * Falls back to the original's URL when no smaller copy can be made; null
 * for anything that is not a public image — the same refusal as
 * `ResolvePublicMediaUrlAction`, so a private file's id gets nothing.
 */
class ResolvePublicImageVariantAction
{
    public function __construct(
        private readonly MediaStorageInterface $storage,
        private readonly ImageProcessorInterface $images,
    ) {}

    public function execute(int $mediaId, int $width): ?string
    {
        $file = MediaFile::query()
            ->whereKey($mediaId)
            ->where('visibility', 'public')
            ->where('disk', 'public')
            ->first();

        if ($file === null || ! str_starts_with((string) $file->mime, 'image/') || ! $this->storage->exists($file->disk, $file->path)) {
            return null;
        }

        $variant = $this->images->getResizedWebPPath($file->path, $width);

        return $this->storage->url($file->disk, $variant ?? $file->path);
    }
}
