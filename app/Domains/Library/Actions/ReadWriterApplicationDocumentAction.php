<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterApplication;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * B9 (§11.1): the identity document attached to a writer application.
 *
 * Resolves the media id from the application row — "the document of
 * application 12" cannot be pivoted into "file 12" — and reaches those
 * documents only. Who may ask is the route's business (`library.manage`,
 * the office); this action never sees a media id from a request.
 */
class ReadWriterApplicationDocumentAction
{
    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}|null
     */
    public function execute(int $applicationId): ?array
    {
        $application = WriterApplication::query()->whereKey($applicationId)->first();
        if ($application === null || $application->id_document_media_file_id === null) {
            return null;
        }

        return app(ReadPrivateMediaAction::class)->execute((int) $application->id_document_media_file_id);
    }
}
