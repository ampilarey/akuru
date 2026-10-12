<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

/**
 * One public media id → its bytes and MIME type, or null. The same gate as
 * `ResolvePublicMediaUrlAction` (public disk, public visibility), for a
 * caller that must embed the file rather than link it — a print file that
 * Chrome opens from disk and must fetch nothing (NAME_TAGS_PLAN §5.2).
 */
class ReadPublicMediaAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /** @return array{id: int, mime: string, content: string}|null */
    public function execute(int $mediaId): ?array
    {
        $file = MediaFile::query()
            ->whereKey($mediaId)
            ->where('visibility', 'public')
            ->where('disk', 'public')
            ->first();

        if ($file === null || ! $this->storage->exists($file->disk, $file->path)) {
            return null;
        }

        return ['id' => (int) $file->id, 'mime' => (string) $file->mime, 'content' => $this->storage->get($file->disk, $file->path)];
    }
}
