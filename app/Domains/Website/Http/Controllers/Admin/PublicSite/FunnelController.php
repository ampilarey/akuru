<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Actions\ComposeCourseFunnelReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FunnelController extends Controller
{
    public function index(Request $request)
    {
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;
        $reports = app(ComposeCourseFunnelReportAction::class)->execute($courseId);

        return view('admin.public-site.funnel.index', [
            'reports' => $reports,
            'courseId' => $courseId,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;
        $rows = app(ComposeCourseFunnelReportAction::class)->execute($courseId);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
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
                fputcsv($out, [
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
