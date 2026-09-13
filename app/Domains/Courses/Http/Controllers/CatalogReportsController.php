<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ComposeCatalogReportsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPEC §33 "Admin Dashboard → Reports".
 *
 * Six of the ten reports §33 names were already computed and lived on three
 * unrelated screens; three had no reader at all. This is the one place that
 * answers the section's question.
 */
class CatalogReportsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render(
            'Courses/Catalog/Reports',
            app(ComposeCatalogReportsAction::class)->execute($this->filters($request)),
        );
    }

    /**
     * Every listing gets a CSV export (CLAUDE.md conventions).
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ComposeCatalogReportsAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['report', 'label', 'value', 'detail']);

            foreach ($payload['totals'] as $key => $value) {
                fputcsv($out, ['total', str_replace('_', ' ', (string) $key), $value ?? '—', '']);
            }
            fputcsv($out, ['scores', 'attempts scored', $payload['scores']['count'], '']);
            fputcsv($out, ['scores', 'average percent', $payload['scores']['average_percent'] ?? '—', '']);
            fputcsv($out, ['reviews', 'pending', $payload['pendingReviews']['count'], $payload['pendingReviews']['oldest'] ?? '']);
            fputcsv($out, ['certificates', 'issued', $payload['certificates']['issued'], '']);
            fputcsv($out, ['certificates', 'revoked', $payload['certificates']['revoked'], '']);

            foreach ($payload['byCourse'] as $row) {
                fputcsv($out, [
                    'course_completion',
                    $row['title'] ?? '',
                    ($row['completed'] ?? 0).'/'.($row['enrolled'] ?? 0),
                    'avg progress '.($row['average_progress'] ?? 0).'%',
                ]);
            }
            foreach ($payload['byOffering'] as $row) {
                fputcsv($out, [
                    'offering_completion',
                    $row['title'] ?? '',
                    ($row['completed'] ?? 0).'/'.($row['enrolled'] ?? 0),
                    'avg attendance '.($row['average_attendance'] ?? '—'),
                ]);
            }

            fclose($out);
        }, 'catalog-reports.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
        ];
    }
}
