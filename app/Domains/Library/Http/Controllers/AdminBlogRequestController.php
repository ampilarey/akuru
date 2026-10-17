<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\DecideBlogRequestAction;
use App\Domains\Library\Actions\Blog\ListBlogRequestsAction;
use App\Domains\Library\Enums\BlogRequestStatus;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BLOG_PLAN BL1: the Library office's list of students' blog requests, with
 * the student's real name beside the pen name, and its CSV. Behind the
 * Library office's gate (`role:super_admin`, `can:library.manage`).
 */
class AdminBlogRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        return Inertia::render('Library/BlogRequests', [
            'requests' => app(ListBlogRequestsAction::class)->office(is_string($status) ? $status : null),
            'statuses' => array_map(fn (BlogRequestStatus $s) => $s->value, BlogRequestStatus::cases()),
            'status' => is_string($status) ? $status : '',
            't' => Phrases::once('blog'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListBlogRequestsAction::class)->office(null, 5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'pen_name', 'blog_title', 'student', 'student_number', 'under_18', 'status', 'parent', 'show_photo', 'show_school', 'asked_at', 'decided_by', 'decided_at', 'note']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['pen_name'], $r['blog_title'], $r['student_name'], $r['student_number'], $r['under_18'] ? 'yes' : 'no', $r['status'], $r['guardian_name'], $r['show_photo'] ? 'yes' : 'no', $r['show_school'] ? 'yes' : 'no', $r['created_at'], $r['decided_by'], $r['decided_at'], $r['decision_note']]);
            }
            fclose($out);
        }, 'blog-requests.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function decide(Request $request, int $blogRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'refuse'])],
            'note' => 'nullable|string|max:500',
        ]);

        app(DecideBlogRequestAction::class)->execute($blogRequest, (int) $request->user()->id, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'approve' ? 'blog.flash_approved' : 'blog.flash_refused'));
    }
}
