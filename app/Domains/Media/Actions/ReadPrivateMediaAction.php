<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

class ReadPrivateMediaAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}|null
     */
    public function execute(int $mediaId): ?array
    {
        $file = MediaFile::query()->find($mediaId);
        if ($file === null || ! $this->storage->exists($file->disk, $file->path)) {
            return null;
        }

        return [
            'id' => $file->id,
            'contents' => $this->storage->get($file->disk, $file->path),
            'mime' => $file->mime,
            'original_name' => $file->original_name,
        ];
    }
}
