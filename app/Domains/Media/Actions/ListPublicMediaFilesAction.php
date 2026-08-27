<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;

class ListPublicMediaFilesAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @param  list<int|string>  $ids
     * @return list<array{id: int, url: string, alt: string}>
     */
    public function execute(array $ids): array
    {
        $ordered = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ordered[] = $intId;
            }
        }
        $ordered = array_values(array_unique($ordered));
        if ($ordered === []) {
            return [];
        }

        $files = MediaFile::query()
            ->whereIn('id', $ordered)
            ->where('visibility', 'public')
            ->where('disk', 'public')
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($ordered as $id) {
            $file = $files->get($id);
            if ($file === null || ! $this->storage->exists($file->disk, $file->path)) {
                continue;
            }

            $meta = is_array($file->meta) ? $file->meta : [];
            $alt = trim((string) ($meta['alt'] ?? $file->original_name));

            $out[] = [
                'id' => $file->id,
                'url' => $this->storage->url($file->disk, $file->path),
                'alt' => $alt !== '' ? $alt : $file->original_name,
            ];
        }

        return $out;
    }
}
