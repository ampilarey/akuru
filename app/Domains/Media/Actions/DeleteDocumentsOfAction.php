<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\Document;

/**
 * Removes the documents one record owns, files and rows, when that record
 * itself is being removed — a print draft nobody ordered (NAME_TAGS_PLAN
 * §7.2, STATUS §5rx). The caller names the owner by its morph alias and id,
 * so it can reach no one else's documents; other domains call this rather
 * than read `documents` (rule 3).
 */
class DeleteDocumentsOfAction
{
    public function execute(string $documentableType, int $documentableId): int
    {
        $storage = app(MediaStorageInterface::class);
        $documents = Document::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->get();

        foreach ($documents as $document) {
            if ($document->media_path && $storage->exists('local', $document->media_path)) {
                $storage->delete('local', $document->media_path);
            }
            $document->delete();
        }

        return $documents->count();
    }
}
