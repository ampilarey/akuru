<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Courses\Actions\ListStudentPerformanceReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalPerformanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render(
            'Portal/Performance',
            app(ListStudentPerformanceReportAction::class)->execute((int) $request->user()->id),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $payload = app(ListStudentPerformanceReportAction::class)->execute((int) $request->user()->id);

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'student',
                'relationship',
                'course',
                'offering',
                'progress_percent',
                'attendance_percent',
                'lessons_completed',
                'lessons_required',
                'status',
                'completed_at',
            ]);
            foreach ($payload['students'] as $student) {
                foreach ($student['rows'] as $row) {
                    fputcsv($out, [
                        $student['name'],
                        $student['relationship'],
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
            }
            fclose($out);
        }, 'performance-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
