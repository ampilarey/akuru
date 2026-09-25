<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

/**
 * One public media id → its URL, or null. Refuses anything not stored on
 * the public disk with public visibility, so a caller holding a private
 * file's id (a paid PDF, a child's recording) gets nothing back — the
 * private path is `ReadPrivateMediaAction`, scoped by its callers.
 */
class ResolvePublicMediaUrlAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    public function execute(int $mediaId): ?string
    {
        $file = MediaFile::query()
            ->whereKey($mediaId)
            ->where('visibility', 'public')
            ->where('disk', 'public')
            ->first();

        if ($file === null || ! $this->storage->exists($file->disk, $file->path)) {
            return null;
        }

        return $this->storage->url($file->disk, $file->path);
    }
}
