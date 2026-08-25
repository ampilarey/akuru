<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\Document;
use Illuminate\Support\Facades\Storage;

class ReadGeneratedDocumentAction
{
    /**
     * @return array{id: int, title: string|null, path: string, contents: string, mime: string}
     */
    public function execute(int $documentId): array
    {
        $document = Document::query()->findOrFail($documentId);
        $disk = Storage::disk('local');
        $exists = app(MediaStorageInterface::class)->exists('local', $document->media_path);

        return [
            'id' => $document->id,
            'title' => $document->title,
            'path' => $document->media_path,
            'contents' => $exists ? $disk->get($document->media_path) : '',
            'mime' => str_ends_with($document->media_path, '.html') ? 'text/html; charset=UTF-8' : 'application/octet-stream',
        ];
    }
}
