<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposePortalHomeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render(
            'Portal/Home',
            app(ComposePortalHomeAction::class)->execute((int) $user->id, $user->isParent()),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $payload = app(ComposePortalHomeAction::class)->execute((int) $user->id, $user->isParent());

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'section',
                'student',
                'relationship',
                'label',
                'detail',
                'status',
                'amount',
            ]);
            foreach ($payload['students'] as $student) {
                $summary = $student['attendance_summary'] ?? [];
                fputcsv($out, [
                    'attendance',
                    $student['name'],
                    $student['relationship'],
                    'percent',
                    $summary['percent'] ?? '',
                    '',
                    '',
                ]);
                foreach ($student['exams'] as $exam) {
                    fputcsv($out, [
                        'exam',
                        $student['name'],
                        $student['relationship'],
                        $exam['name'] ?? '',
                        $exam['subject'] ?? '',
                        $exam['marks'] === null ? '' : $exam['marks'].'/'.$exam['max_marks'],
                        '',
                    ]);
                }
                foreach ($student['invoices'] as $invoice) {
                    fputcsv($out, [
                        'invoice',
                        $student['name'],
                        $student['relationship'],
                        $invoice['invoice_number'] ?? '',
                        $invoice['due_date'] ?? '',
                        $invoice['status'] ?? '',
                        $invoice['balance'] ?? '',
                    ]);
                }
                foreach ($student['courses'] as $course) {
                    fputcsv($out, [
                        'course',
                        $student['name'],
                        $student['relationship'],
                        $course['course_title'] ?? '',
                        $course['offering_title'] ?? '',
                        $course['status'] ?? '',
                        ($course['progress_percentage'] ?? '').'%',
                    ]);
                }
                foreach ($student['hifz'] as $row) {
                    fputcsv($out, [
                        'hifz',
                        $student['name'],
                        $student['relationship'],
                        $row['program'] ?: ($row['current_surah'] ?? ''),
                        $row['current_surah'] ?? '',
                        $row['status'] ?? '',
                        $row['accuracy_percent'] ?? '',
                    ]);
                }
            }
            fclose($out);
        }, 'portal-home.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
