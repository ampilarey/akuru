<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListGateMovementsAction;
use App\Domains\Academics\Actions\RecordStudentMovementAction;
use App\Domains\Academics\Actions\VoidStudentMovementAction;
use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The gate console. Thin (rule 5).
 *
 * Search-driven rather than a roster of the whole school: somebody standing at
 * a gate deals with one child at a time, and a list of nine hundred names is
 * slower than typing three letters.
 */
class GateMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $date = (string) $request->query('date', now()->toDateString());
        $query = trim((string) $request->query('q', ''));

        $gate = app(ListGateMovementsAction::class);
        $matches = $query === '' ? [] : app(SearchRosterCandidatesAction::class)->execute($query, 12);

        // So the operator can see "this child is already marked in" before they
        // tap, rather than discovering it in the log afterwards.
        $state = $gate->currentStateFor(array_column($matches, 'id'));
        $matches = array_map(fn (array $row): array => $row + ['current' => $state[$row['id']] ?? null], $matches);

        $lists = $gate->execute($date);

        return Inertia::render('Academics/Gate/Console', [
            'date' => $date,
            'q' => $query,
            'matches' => $matches,
            'movements' => $lists['movements'],
            'in_count' => $lists['in_count'],
            'out_count' => $lists['out_count'],
        ]);
    }

    public function record(Request $request, RecordStudentMovementAction $record): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'direction' => ['required', 'string', 'in:in,out'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $record->execute(
            (int) $data['student_id'],
            MovementDirection::from($data['direction']),
            (int) $request->user()->id,
            note: $data['note'] ?? null,
        );

        return back()->with('success', 'Recorded at the gate.');
    }

    public function void(Request $request, int $movement, VoidStudentMovementAction $void): RedirectResponse
    {
        $void->execute($movement, (int) $request->user()->id);

        return back()->with('success', 'Taken back. The record shows it was corrected.');
    }
}
