<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Models\Document;

class StoreDocumentAction
{
    /**
     * @param  array{documentable_type: string, documentable_id: int, media_path: string, document_type?: string, title?: string|null, expires_at?: string|null, uploaded_by?: int|null}  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $document = Document::query()->create([
            'documentable_type' => $data['documentable_type'],
            'documentable_id' => (int) $data['documentable_id'],
            'media_path' => $data['media_path'],
            'document_type' => $data['document_type'] ?? 'other',
            'title' => $data['title'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
        ]);

        return [
            'id' => $document->id,
            'documentable_type' => $document->documentable_type,
            'documentable_id' => $document->documentable_id,
            'document_type' => $document->document_type instanceof \BackedEnum
                ? $document->document_type->value
                : (string) $document->document_type,
            'title' => $document->title,
            'expires_at' => $document->expires_at?->toDateString(),
        ];
    }
}
