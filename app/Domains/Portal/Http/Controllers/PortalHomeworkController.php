<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Academics\Actions\TickHomeworkAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E3a — homework, read by the people it is set for.
 *
 * `lesson_logs.homework` has been filled by teachers since 2025 and never shown
 * to a student or parent. This is the reader.
 */
class PortalHomeworkController extends Controller
{
    public function index(Request $request): Response
    {
        $people = $this->people($request);
        $reader = app(ListHomeworkForStudentAction::class);

        return Inertia::render('Portal/Homework', [
            'students' => array_map(fn (array $person): array => [
                ...$person,
                'homework' => $reader->execute($person['id'])->all(),
            ], $people),
            // A parent reads; only the pupil ticks their own work.
            'canTick' => array_values(array_filter(
                array_map(
                    fn (array $person): ?int => $person['relationship'] === 'self' ? $person['id'] : null,
                    $people,
                ),
            )),
        ]);
    }

    public function tick(Request $request, int $lessonLog): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'done' => ['sometimes', 'boolean'],
        ]);

        // A guardian may see a child's homework but not tick it done on their
        // behalf: the tick is the pupil's own statement about their own work.
        $self = app(ResolveStudentForUserAction::class)->execute($this->userId($request));
        abort_unless($self !== null && (int) $self['id'] === (int) $data['student_id'], 403);

        app(TickHomeworkAction::class)->execute(
            (int) $data['student_id'],
            $lessonLog,
            (bool) ($data['done'] ?? true),
        );

        return redirect()->route('portal.homework')->with('success', 'Updated.');
    }

    /**
     * @return list<array{id: int, name: string, relationship: string}>
     */
    private function people(Request $request): array
    {
        $userId = $this->userId($request);
        $people = [];

        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $people[] = [
                'id' => (int) $self['id'],
                'name' => trim($self['first_name'].' '.$self['last_name']),
                'relationship' => 'self',
            ];
        }

        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            if (collect($people)->contains(fn (array $person): bool => $person['id'] === (int) $child->id)) {
                continue;
            }
            $people[] = [
                'id' => (int) $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                'relationship' => (string) ($child->relationship ?? 'child'),
            ];
        }

        return $people;
    }

    private function userId(Request $request): int
    {
        abort_unless($request->user() !== null, 403);

        return (int) $request->user()->id;
    }
}
