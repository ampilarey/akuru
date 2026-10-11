<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;

class GenerateTransferCertificateAction
{
    /**
     * @return array{html: string, document_id: int}
     */
    public function execute(int $studentId, ?int $actorId = null, ?string $locale = null): array
    {
        // The document is written in the language of the page that asked for it (slice DOC2).
        $locale = $locale ?? app()->getLocale();
        $student = DB::table('students')->where('id', $studentId)->firstOrFail();
        $history = DB::table('student_status_history')
            ->where('student_id', $studentId)
            ->orderBy('effective_date')
            ->get()
            ->map(fn ($row) => [
                'from' => $row->from_status,
                'to' => $row->to_status,
                'reason' => $row->reason,
                'effective_date' => $row->effective_date,
            ])
            ->all();

        $html = app(DocumentRendererInterface::class)->render('transfer-certificate', [
            'locale' => $locale,
            'dir' => in_array($locale, ['dv', 'ar'], true) ? 'rtl' : 'ltr',
            'student' => [
                'id' => $studentId,
                'name' => PersonName::ofStudent($student),
                'number' => $student->student_id ?? null,
                'status' => $student->status ?? null,
            ],
            'history' => $history,
        ]);

        $document = app(StoreGeneratedDocumentAction::class)->execute(
            'student',
            $studentId,
            'transfer_certificate',
            sprintf('Transfer certificate — %s', PersonName::ofStudent($student)),
            $html,
            'html',
            $actorId,
        );

        return ['html' => $html, 'document_id' => $document->id];
    }
}
