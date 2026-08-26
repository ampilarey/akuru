<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListOfferingCompletionReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseCompletionReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render(
            'Courses/Catalog/CompletionReports',
            app(ListOfferingCompletionReportAction::class)->execute($this->filters($request)),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListOfferingCompletionReportAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'student',
                'course',
                'offering',
                'progress_percent',
                'attendance_percent',
                'lessons_completed',
                'lessons_required',
                'status',
                'completed_at',
            ]);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, [
                    $row['student_name'],
                    $row['course_title'],
                    $row['offering_title'],
                    $row['progress_percentage'],
                    $row['attendance_percent'],
                    $row['lessons_completed'],
                    $row['lessons_required'],
                    $row['status'],
                    $row['completed_at'],
                ]);
            }
            fclose($out);
        }, 'completion-reports.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{academic_year_id?: int|null, offering_id?: int|null, course_id?: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'offering_id' => $request->integer('offering_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
        ];
    }
}
