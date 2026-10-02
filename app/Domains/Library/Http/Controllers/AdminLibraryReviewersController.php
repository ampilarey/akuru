<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ManageReviewerPoolAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RESEARCH_ARTICLES_PLAN R3b: the peer-reviewer pool, on a screen. Gated
 * with the rest of the Library office (`role:super_admin` + `library.manage`).
 */
class AdminLibraryReviewersController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Library/Reviewers', [
            'reviewers' => app(ManageReviewerPoolAction::class)->list(),
            't' => Phrases::once('admin'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = app(ManageReviewerPoolAction::class)->list();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['name', 'email', 'open', 'overdue', 'done', 'average_days']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['name'], $row['email'], $row['open'], $row['overdue'], $row['done'], $row['average_days'] ?? '']);
            }
            fclose($out);
        }, 'library-reviewers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        app(ManageReviewerPoolAction::class)->add($data['email']);

        return back()->with('success', trans('admin.library_reviewers_added'));
    }

    public function destroy(int $user): RedirectResponse
    {
        app(ManageReviewerPoolAction::class)->remove($user);

        return back()->with('success', trans('admin.library_reviewers_removed'));
    }
}
