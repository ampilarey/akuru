<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Models\Document;
use Illuminate\Support\Facades\Storage;

class ReadDocumentContentAction
{
    /**
     * @return array{id: int, content: string, media_path: string, mime: string}|null
     */
    public function execute(int $documentId): ?array
    {
        $document = Document::query()->find($documentId);
        if ($document === null || ! Storage::disk('local')->exists($document->media_path)) {
            return null;
        }

        // Rendered documents are HTML; uploaded ones (StoreUploadedDocumentAction)
        // are whatever was sniffed on the way in. The disk knows which.
        $mime = str_ends_with($document->media_path, '.html')
            ? 'text/html; charset=UTF-8'
            : ((string) (Storage::disk('local')->mimeType($document->media_path) ?: 'application/octet-stream'));

        return [
            'id' => $document->id,
            'content' => (string) Storage::disk('local')->get($document->media_path),
            'media_path' => $document->media_path,
            'mime' => $mime,
        ];
    }
}
