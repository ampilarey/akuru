<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Jobs\ProcessMediaFileJob;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Support\Str;

/**
 * A private copy of a public file (RESEARCH_ARTICLES_PLAN R2): the website's
 * research PDFs were public media, linked straight from the page; in the
 * Digital Library a PDF is a private original that only the access check
 * hands out. The public file is left where it is — rule 9 — and the copy is
 * a new private row.
 *
 * Reads only a file that is public already, so it can never be used to
 * surface a private one.
 */
class CopyPublicMediaToPrivateAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @param  list<string>  $allowedMimes
     * @return array{id: int, mime: string, original_name: string}|null
     */
    public function execute(int $publicMediaId, ?int $uploadedBy = null, array $allowedMimes = [], string $folder = 'library-imports'): ?array
    {
        $source = MediaFile::query()
            ->whereKey($publicMediaId)
            ->where('visibility', 'public')
            ->first();
        if ($source === null || ! $this->storage->exists($source->disk, $source->path)) {
            return null;
        }
        if ($allowedMimes !== [] && ! in_array($source->mime, $allowedMimes, true)) {
            return null;
        }

        $contents = $this->storage->get($source->disk, $source->path);
        $extension = strtolower(pathinfo((string) $source->path, PATHINFO_EXTENSION));
        $path = $folder.'/'.now()->format('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $this->storage->put('local', $path, $contents);

        $copy = MediaFile::query()->create([
            'disk' => 'local',
            'path' => $path,
            'mime' => $source->mime,
            'original_name' => $source->original_name,
            'size' => strlen($contents),
            'uploaded_by' => $uploadedBy,
            'visibility' => 'private',
            'process_status' => 'pending',
        ]);

        ProcessMediaFileJob::dispatch($copy->id);

        return ['id' => $copy->id, 'mime' => $copy->mime, 'original_name' => $copy->original_name];
    }
}
