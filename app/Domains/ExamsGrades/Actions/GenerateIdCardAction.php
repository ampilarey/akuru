<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Services\StudentNumberQr;
use Illuminate\Support\Facades\DB;

class GenerateIdCardAction
{
    /**
     * @return array{html: string, document_id: int}
     */
    public function execute(int $studentId, ?int $actorId = null): array
    {
        $student = DB::table('students')->where('id', $studentId)->firstOrFail();
        $number = $student->student_id ?: (string) $student->id;
        $photo = DB::table('documents')
            ->where('documentable_type', 'student')
            ->where('documentable_id', $studentId)
            ->where('document_type', 'photo')
            ->orderByDesc('id')
            ->value('media_path');

        $html = app(DocumentRendererInterface::class)->render('id-card', [
            'locale' => 'en',
            'dir' => 'ltr',
            'student' => [
                'id' => $studentId,
                'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'number' => $number,
                'photo' => $photo,
            ],
            'qr' => app(StudentNumberQr::class)->svg($number),
        ]);

        $document = app(StoreGeneratedDocumentAction::class)->execute(
            'student',
            $studentId,
            'id_card',
            sprintf('ID card — %s', trim(($student->first_name ?? '').' '.($student->last_name ?? ''))),
            $html,
            'html',
            $actorId,
        );

        return ['html' => $html, 'document_id' => $document->id];
    }
}
