<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ReportCardCommentType;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\ReportCardComment;
use Illuminate\Validation\ValidationException;

class SaveReportCardCommentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $authorId): ReportCardComment
    {
        $card = ReportCard::query()->findOrFail((int) $data['report_card_id']);
        $type = ReportCardCommentType::from((string) $data['comment_type']);
        $comment = trim((string) ($data['comment'] ?? ''));
        if ($comment === '') {
            throw ValidationException::withMessages(['comment' => 'Comment is required.']);
        }

        return ReportCardComment::query()->updateOrCreate(
            [
                'report_card_id' => $card->id,
                'comment_type' => $type,
            ],
            [
                'comment' => $comment,
                'comment_arabic' => $this->nullable($data['comment_arabic'] ?? null),
                'comment_dhivehi' => $this->nullable($data['comment_dhivehi'] ?? null),
                'author_id' => $authorId,
            ],
        );
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
