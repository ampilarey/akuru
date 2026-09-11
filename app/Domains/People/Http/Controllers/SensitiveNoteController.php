<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\People\Actions\ArchiveSensitiveNoteAction;
use App\Domains\People\Actions\ListSensitiveNotesAction;
use App\Domains\People\Actions\ListSensitiveNoteViewsAction;
use App\Domains\People\Actions\SaveSensitiveNoteAction;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Domains\People\Enums\SensitiveNoteCategory;
use App\Domains\People\Models\StudentSensitiveNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E19. Thin (rule 5) — and the route group, not this class, is what restricts
 * access: `role:super_admin|headmaster` plus `can:sensitive.read`.
 *
 * **There is no family-facing side and no CSV export**, both deliberate. The
 * repo convention that every listing gets a CSV is the wrong default for the
 * one table where "somebody exported it" is the incident.
 */
class SensitiveNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $studentId = (int) $request->query('student_id', 0);
        $viewerId = (int) $request->user()->id;

        return Inertia::render('People/Sensitive/Index', [
            'q' => $query,
            'student_id' => $studentId ?: null,
            'matches' => $query === '' ? [] : app(SearchRosterCandidatesAction::class)->execute($query, 12),
            'categories' => array_map(
                fn (SensitiveNoteCategory $c): array => ['value' => $c->value, 'label' => $c->label()],
                SensitiveNoteCategory::cases(),
            ),
            // Reading is what gets logged, so it happens only when a pupil is
            // actually chosen — not on every visit to the search box.
            'notes' => $studentId > 0
                ? app(ListSensitiveNotesAction::class)->execute($studentId, $viewerId, includeArchived: true)
                : collect(),
            'views' => $studentId > 0
                ? app(ListSensitiveNoteViewsAction::class)->execute($studentId)
                : collect(),
        ]);
    }

    public function store(Request $request, SaveSensitiveNoteAction $save): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', 'max:20'],
            'summary' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:4000'],
            'review_on' => ['nullable', 'date'],
        ]);

        $save->execute($data, (int) $request->user()->id);

        return back()->with('success', 'Recorded. Your name is on it, and every read of this pupil is logged.');
    }

    public function update(Request $request, StudentSensitiveNote $note, SaveSensitiveNoteAction $save): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:20'],
            'summary' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:4000'],
            'review_on' => ['nullable', 'date'],
        ]);

        $save->execute($data, (int) $request->user()->id, $note);

        return back()->with('success', 'Updated.');
    }

    public function archive(Request $request, int $note, ArchiveSensitiveNoteAction $archive): RedirectResponse
    {
        $archive->execute($note, (int) $request->user()->id);

        return back()->with('success', 'Archived. It stays on the record — nothing here is deleted.');
    }
}
