<?php

namespace App\Domains\Courses\Components\Arabic\Http\Controllers;

use App\Domains\Courses\Components\Arabic\Actions\ListArabicSkillReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogArabicReportController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListArabicSkillReportAction::class)->execute(
            $request->filled('course_id') ? (int) $request->input('course_id') : null,
        );

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['activity_id', 'title', 'skill', 'letter', 'harakah', 'attempts', 'average_score']);
                foreach ($payload['rows'] as $row) {
                    fputcsv($handle, [
                        $row['activity_id'],
                        $row['title'],
                        $row['skill'],
                        $row['letter']['display_name'] ?? '',
                        $row['harakah']['display_name'] ?? '',
                        $row['attempts'],
                        $row['average_score'],
                    ]);
                }
                fclose($handle);
            }, 'arabic-skill-report.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Catalog/ArabicReport', $payload);
    }
}
