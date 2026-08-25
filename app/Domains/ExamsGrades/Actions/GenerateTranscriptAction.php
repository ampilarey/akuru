<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\TermGrade;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Facades\DB;

class GenerateTranscriptAction
{
    /**
     * @return array{html: string, document_id: int, gpa: float|null, rows: list<array<string, mixed>>}
     */
    public function execute(int $studentId, string $locale = 'en', ?int $actorId = null): array
    {
        $student = DB::table('students')->where('id', $studentId)->first();
        $grades = TermGrade::query()
            ->where('student_id', $studentId)
            ->orderBy('academic_year_id')
            ->orderBy('term_id')
            ->orderBy('subject_id')
            ->get();

        $subjects = DB::table('subjects')->whereIn('id', $grades->pluck('subject_id'))->pluck('name', 'id');
        $terms = DB::table('terms')->whereIn('id', $grades->pluck('term_id'))->get()->keyBy('id');
        $years = DB::table('academic_years')->whereIn('id', $grades->pluck('academic_year_id'))->pluck('name', 'id');

        $rows = $grades->map(function (TermGrade $grade) use ($subjects, $terms, $years) {
            $term = $terms[$grade->term_id] ?? null;

            return [
                'year' => $years[$grade->academic_year_id] ?? null,
                'term' => $term->name ?? null,
                'subject' => $subjects[$grade->subject_id] ?? (string) $grade->subject_id,
                'percent' => $grade->weighted_percent,
                'grade' => $grade->grade,
                'point' => $grade->grade_point,
            ];
        })->values()->all();

        $points = $grades->pluck('grade_point')->filter(fn ($point) => $point !== null);
        $gpa = $points->isEmpty() ? null : round((float) $points->avg(), 2);

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

        $rtl = in_array($locale, ['dv', 'ar'], true);
        $payload = [
            'locale' => $locale,
            'dir' => $rtl ? 'rtl' : 'ltr',
            'student' => [
                'id' => $studentId,
                'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'number' => $student->student_id ?? null,
            ],
            'rows' => $rows,
            'gpa' => $gpa,
            'history' => $history,
        ];

        $html = app(DocumentRendererInterface::class)->render('transcript', $payload);
        $document = app(StoreGeneratedDocumentAction::class)->execute(
            'student',
            $studentId,
            'transcript',
            sprintf('Transcript — %s', $payload['student']['name']),
            $html,
            'html',
            $actorId,
        );

        return [
            'html' => $html,
            'document_id' => $document->id,
            'gpa' => $gpa,
            'rows' => $rows,
        ];
    }
}
