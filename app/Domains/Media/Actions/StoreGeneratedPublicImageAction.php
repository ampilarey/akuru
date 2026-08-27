<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

class StoreGeneratedPublicImageAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{id: int, url: string, path: string, mime: string}
     */
    public function execute(
        string $contents,
        string $path,
        string $originalName = 'image.png',
        string $mime = 'image/png',
        ?int $uploadedBy = null,
        array $meta = [],
    ): array {
        $this->storage->put('public', $path, $contents);

        $media = MediaFile::query()->updateOrCreate(
            ['disk' => 'public', 'path' => $path],
            [
                'mime' => $mime,
                'original_name' => $originalName,
                'size' => strlen($contents),
                'uploaded_by' => $uploadedBy,
                'visibility' => 'public',
                'process_status' => 'processed',
                'processed_at' => now(),
                'meta' => $meta,
            ],
        );

        return [
            'id' => $media->id,
            'url' => $this->storage->url('public', $path),
            'path' => $path,
            'mime' => $media->mime,
        ];
    }
}
