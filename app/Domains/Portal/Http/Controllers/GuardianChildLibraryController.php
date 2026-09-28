<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Library\Actions\ListReaderLibraryForFamilyAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B8 (LIBRARY_PLAN §10): a parent's view of a child's library — what the
 * child is reading and has bought. Verified children only (item 13), and
 * only the shelves a family may see (`ListReaderLibraryForFamilyAction`).
 */
class GuardianChildLibraryController extends Controller
{
    public function show(Request $request, int $student): Response
    {
        $child = $this->child($request, $student);
        $library = $child->user_id
            ? app(ListReaderLibraryForFamilyAction::class)->execute((int) $child->user_id)
            : ['continue' => [], 'purchases' => []];

        return Inertia::render('Portal/ChildLibrary', [
            'child' => [
                'id' => (int) $child->id,
                'name' => trim($child->first_name.' '.$child->last_name),
                'has_account' => $child->user_id !== null,
            ],
            'continue' => $library['continue'],
            'purchases' => $library['purchases'],
        ]);
    }

    public function export(Request $request, int $student): StreamedResponse
    {
        $child = $this->child($request, $student);
        $library = $child->user_id
            ? app(ListReaderLibraryForFamilyAction::class)->execute((int) $child->user_id)
            : ['continue' => [], 'purchases' => []];

        return response()->streamDownload(function () use ($library): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['kind', 'title', 'page', 'progress_percent', 'completed', 'last_read_at', 'minutes_read', 'amount', 'status', 'purchased_at']);
            foreach ($library['continue'] as $row) {
                Csv::put($out, ['reading', $row['title'], $row['current_page'], $row['progress_percent'], $row['completed'] ? 'yes' : 'no', $row['last_read_at'], $row['reading_minutes'], '', '', '']);
            }
            foreach ($library['purchases'] as $row) {
                Csv::put($out, ['purchase', $row['title'], '', '', '', '', '', $row['amount'], $row['status'], $row['purchased_at']]);
            }
            fclose($out);
        }, 'child-library.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The named child, if they are one of this parent's verified children. */
    private function child(Request $request, int $student): object
    {
        $child = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->first(fn (object $row) => (int) $row->id === $student);
        abort_if($child === null, 403);

        return $child;
    }
}
