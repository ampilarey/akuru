<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadPublishedReportCardAction
{
    /**
     * @return array{id: int, title: string|null, path: string, contents: string, mime: string}
     */
    public function execute(int $reportCardId, int $guardianUserId): array
    {
        $card = ReportCard::query()->findOrFail($reportCardId);
        if ($card->status !== ReportCardStatus::Published || ! $card->document_id) {
            throw new HttpException(404, 'Report card is not available.');
        }

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);
        if (! $children->pluck('id')->contains($card->student_id)) {
            throw new HttpException(403, 'Not authorized to download this report card.');
        }

        return app(ReadGeneratedDocumentAction::class)->execute((int) $card->document_id);
    }
}
