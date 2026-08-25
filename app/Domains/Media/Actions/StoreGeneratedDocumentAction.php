<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Enums\DocumentType;
use App\Domains\Media\Models\Document;

class StoreGeneratedDocumentAction
{
    public function execute(
        string $documentableType,
        int $documentableId,
        DocumentType|string $documentType,
        string $title,
        string $contents,
        string $extension = 'html',
        ?int $uploadedBy = null,
    ): Document {
        $type = $documentType instanceof DocumentType
            ? $documentType
            : DocumentType::from($documentType);

        $path = sprintf(
            'documents/%s/%d/%s-%s.%s',
            $documentableType,
            $documentableId,
            $type->value,
            now()->format('YmdHis'),
            $extension,
        );

        app(MediaStorageInterface::class)->put('local', $path, $contents);

        return Document::query()->create([
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'media_path' => $path,
            'document_type' => $type,
            'title' => $title,
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
