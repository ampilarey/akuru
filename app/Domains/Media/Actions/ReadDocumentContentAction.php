<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Models\Document;
use Illuminate\Support\Facades\Storage;

class ReadDocumentContentAction
{
    /**
     * @return array{id: int, content: string, media_path: string}|null
     */
    public function execute(int $documentId): ?array
    {
        $document = Document::query()->find($documentId);
        if ($document === null || ! Storage::disk('local')->exists($document->media_path)) {
            return null;
        }

        return [
            'id' => $document->id,
            'content' => (string) Storage::disk('local')->get($document->media_path),
            'media_path' => $document->media_path,
        ];
    }
}
