<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\HideStudentWorkAction;
use App\Domains\Academics\Actions\ListStudentWorkAction;
use App\Domains\Academics\Actions\ReadStudentWorkPhotoAction;
use App\Domains\Academics\Actions\ReassignStudentWorkAction;
use App\Domains\Academics\Actions\SaveStudentWorkAction;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The staff side of E21. Thin (rule 5). */
class StudentWorkController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('Academics/Work/Index', [
            'q' => $query,
            'matches' => $query === '' ? [] : app(SearchRosterCandidatesAction::class)->execute($query, 12),
            'work' => app(ListStudentWorkAction::class)->execute(),
        ]);
    }

    /**
     * CLAUDE.md: *"every listing gets CSV export."*
     *
     * The photographs themselves are private media and stay behind
     * `work/{work}/photo`; this is the log of what was photographed, for whom,
     * by whom — including `times_moved`, which is the column somebody auditing
     * a mis-assigned photo would come here for.
     */
    public function export(ListStudentWorkAction $list): StreamedResponse
    {
        $rows = $list->execute(limit: 5000);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'student', 'student_number', 'title', 'note', 'done_on', 'uploaded_by', 'hidden', 'times_moved']);

            foreach ($rows as $row) {
                Csv::put($handle, [
                    $row['id'],
                    $row['student'],
                    $row['student_number'],
                    $row['title'],
                    $row['note'],
                    $row['done_on'],
                    $row['uploaded_by'],
                    $row['hidden'] ? 'yes' : 'no',
                    $row['times_moved'],
                ]);
            }

            fclose($handle);
        }, 'student-work.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, SaveStudentWorkAction $save): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'photo' => ['required', 'file', 'max:8192'],
            'title' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:191'],
            'done_on' => ['nullable', 'date'],
        ]);

        $save->execute($data, (int) $request->user()->id, $request->file('photo'));

        return back()->with('success', 'Saved, and the family can see it.');
    }

    public function reassign(Request $request, int $work, ReassignStudentWorkAction $reassign): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer', 'min:1']]);

        $reassign->execute($work, (int) $data['student_id'], (int) $request->user()->id);

        return back()->with('success', 'Moved. The first family can no longer see it.');
    }

    public function hide(Request $request, int $work, HideStudentWorkAction $hide): RedirectResponse
    {
        $hide->execute($work, (int) $request->user()->id);

        return back()->with('success', 'Hidden from families.');
    }

    public function restore(int $work, HideStudentWorkAction $hide): RedirectResponse
    {
        $hide->restore($work);

        return back()->with('success', 'Visible to families again.');
    }

    public function photo(int $work, ReadStudentWorkPhotoAction $read): HttpResponse
    {
        $media = $read->execute($work);

        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($media['original_name']).'"',
        ]);
    }
}
