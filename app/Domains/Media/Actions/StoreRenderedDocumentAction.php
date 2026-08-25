<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Enums\DocumentType;
use App\Domains\Media\Models\Document;
use Illuminate\Support\Facades\Storage;

class StoreRenderedDocumentAction
{
    /**
     * @return array{id: int, path: string}
     */
    public function execute(
        string $documentableType,
        int $documentableId,
        string $title,
        string $html,
        ?int $uploadedBy = null,
        string $type = 'other',
    ): array {
        $path = 'documents/'.uniqid('doc_', true).'.html';
        Storage::disk('local')->put($path, $html);

        $document = Document::query()->create([
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'media_path' => $path,
            'document_type' => DocumentType::tryFrom($type) ?? DocumentType::Other,
            'title' => $title,
            'uploaded_by' => $uploadedBy,
        ]);

        return ['id' => $document->id, 'path' => $path];
    }
}
