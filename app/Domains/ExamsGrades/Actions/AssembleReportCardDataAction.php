<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\Academics\Actions\ListBehaviorRecordsAction;
use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\ExamsGrades\Enums\ReportCardCommentType;
use App\Domains\ExamsGrades\Models\CompetencyAssessment;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\ExamsGrades\Models\TermGrade;
use Illuminate\Support\Facades\DB;

class AssembleReportCardDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(ReportCard $card, string $locale = 'en'): array
    {
        $template = $card->template ?? ReportCardTemplate::query()->findOrFail($card->template_id);
        $term = DB::table('terms')->where('id', $card->term_id)->first();
        $year = $term ? DB::table('academic_years')->where('id', $term->academic_year_id)->first() : null;
        $class = DB::table('classes')->where('id', $card->class_id)->first();
        $student = DB::table('students')->where('id', $card->student_id)->first();

        $grades = TermGrade::query()
            ->where('student_id', $card->student_id)
            ->where('term_id', $card->term_id)
            ->where('class_id', $card->class_id)
            ->get();
        $subjects = DB::table('subjects')->whereIn('id', $grades->pluck('subject_id'))->pluck('name', 'id');

        $assessments = CompetencyAssessment::query()
            ->with('competency')
            ->where('student_id', $card->student_id)
            ->where('term_id', $card->term_id)
            ->get();

        $attendance = app(ListClassAttendanceAction::class)
            ->studentSummary($card->student_id, $year->id ?? null)
            ->first();

        $behavior = app(ListBehaviorRecordsAction::class)->execute([
            'student_id' => $card->student_id,
            'academic_year_id' => $year->id ?? null,
            'parent_visible' => true,
        ]);

        $comments = $card->comments()->get()->keyBy(fn ($row) => $row->comment_type->value);

        $rtl = in_array($locale, ['dv', 'ar'], true);

        return [
            'locale' => $locale,
            'dir' => $rtl ? 'rtl' : 'ltr',
            'template' => [
                'name' => $template->name,
                'header' => $template->header,
                'footer' => $template->footer,
                'sections' => $template->sections ?? [],
            ],
            'student' => [
                'id' => (int) $card->student_id,
                'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'number' => $student->student_id ?? null,
            ],
            'class' => [
                'id' => (int) $card->class_id,
                'name' => trim(($class->name ?? '').' '.($class->section ?? '')),
            ],
            'term' => [
                'id' => (int) $card->term_id,
                'name' => $term->name ?? null,
                'year' => $year->name ?? null,
            ],
            'grades' => $grades->map(fn (TermGrade $grade) => [
                'subject' => $subjects[$grade->subject_id] ?? (string) $grade->subject_id,
                'percent' => $grade->weighted_percent,
                'grade' => $grade->grade,
                'point' => $grade->grade_point,
                'rank' => $grade->rank,
            ])->values()->all(),
            'competencies' => $assessments->map(fn (CompetencyAssessment $row) => [
                'name' => $row->competency?->name,
                'level' => $row->level,
                'notes' => $row->notes,
            ])->values()->all(),
            'attendance' => $attendance ?? [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
                'left_early' => 0,
                'percent' => 0,
            ],
            'behavior' => [
                'total' => $behavior->count(),
                'items' => $behavior->map(fn (array $row) => [
                    'type' => $row['type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'date' => $row['date'],
                    'points' => $row['points'],
                ])->values()->all(),
            ],
            'comments' => [
                'class_teacher' => $this->commentText($comments[ReportCardCommentType::ClassTeacher->value] ?? null, $locale),
                'head' => $this->commentText($comments[ReportCardCommentType::Head->value] ?? null, $locale),
            ],
            'awards' => StudentAward::query()
                ->with('award')
                ->where('student_id', $card->student_id)
                ->where('academic_year_id', $year->id ?? 0)
                ->when($card->term_id, fn ($query) => $query->where(function ($inner) use ($card): void {
                    $inner->whereNull('term_id')->orWhere('term_id', $card->term_id);
                }))
                ->get()
                ->map(fn (StudentAward $row) => [
                    'title' => $row->award?->title,
                    'date' => $row->awarded_date?->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function commentText(mixed $comment, string $locale): ?string
    {
        if ($comment === null) {
            return null;
        }

        return match ($locale) {
            'dv' => $comment->comment_dhivehi ?: $comment->comment,
            'ar' => $comment->comment_arabic ?: $comment->comment,
            default => $comment->comment,
        };
    }
}
