<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ComposeCourseFunnelReportAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The course funnel report (W16, ADR-022). Inertia since C9 slice 6 (STATUS
 * §5jh), with its UI strings keyed for Dhivehi and Arabic; the decision
 * sentence is the domain's, the same on the page and in the CSV.
 * `role:super_admin` on the route group.
 */
class FunnelController extends Controller
{
    public function index(Request $request): Response
    {
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;

        return Inertia::render('Website/Funnel', [
            'reports' => app(ComposeCourseFunnelReportAction::class)->execute($courseId),
            'course_id' => $courseId,
            't' => trans('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;
        $rows = app(ComposeCourseFunnelReportAction::class)->execute($courseId);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, [
                'course_id',
                'course_title',
                'course_view',
                'register_click',
                'registration_started',
                'payment_completed',
                'whatsapp_click',
                'syllabus_download',
                'view_to_register',
                'register_to_started',
                'started_to_paid',
                'decision',
            ]);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['course_id'],
                    $row['course_title'],
                    $row['counts']['course_view'],
                    $row['counts']['register_click'],
                    $row['counts']['registration_started'],
                    $row['counts']['payment_completed'],
                    $row['counts']['whatsapp_click'],
                    $row['counts']['syllabus_download'],
                    $row['rates']['view_to_register'] ?? '',
                    $row['rates']['register_to_started'] ?? '',
                    $row['rates']['started_to_paid'] ?? '',
                    $row['decision'],
                ]);
            }
            fclose($out);
        }, 'funnel.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
