<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Enums\DocumentType;
use App\Domains\Media\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A file somebody uploaded, kept as a `documents` row on the private disk —
 * the counterpart of `StoreRenderedDocumentAction`, which stores what the
 * application rendered. First use: the supporting document a leave type can
 * require (S5.2 `requires_document`, STATUS §5ff).
 *
 * MIME by sniffing, not extension (SPEC §30), and a size cap enforced here
 * as well as in the request rules: this is the one funnel.
 */
class StoreUploadedDocumentAction
{
    public const ALLOWED = ['application/pdf', 'image/jpeg', 'image/png'];

    public const MAX_BYTES = 5 * 1048576;

    /**
     * @return array{id: int, path: string}
     */
    public function execute(
        UploadedFile $file,
        string $documentableType,
        int $documentableId,
        string $title,
        string $type = 'other',
        ?int $uploadedBy = null,
    ): array {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        if (! in_array($mime, self::ALLOWED, true)) {
            throw ValidationException::withMessages(['document' => 'A PDF, JPEG or PNG.']);
        }
        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['document' => 'That file is larger than 5 MB.']);
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $path = 'documents/uploads/'.now()->format('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        Storage::disk('local')->put($path, (string) file_get_contents($file->getRealPath()));

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
