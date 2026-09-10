<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListSubjectsAction;
use App\Domains\Academics\Actions\ListTeachingMaterialsAction;
use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Models\TeachingMaterial;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeUse($request);

        $filters = $this->filters($request);

        return Inertia::render('Academics/Materials/Index', [
            'filters' => [
                'q' => $filters['q'],
                'subject_id' => $filters['subject_id'],
                'tag' => $filters['tag'],
                'mine' => $filters['mine_for'] !== null,
            ],
            'materials' => app(ListTeachingMaterialsAction::class)->execute($filters),
            'subjects' => app(ListSubjectsAction::class)->execute(),
            'userId' => (int) $request->user()->id,
        ]);
    }

    /**
     * The same list the screen shows, as a file — the filters are honoured so a
     * teacher exports what they were looking at, not everything.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeUse($request);

        $materials = app(ListTeachingMaterialsAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($materials): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'title', 'subject', 'tags', 'author', 'body']);

            foreach ($materials as $material) {
                fputcsv($handle, [
                    $material['id'],
                    $material['title'],
                    $material['subject'] ?? '',
                    implode(', ', $material['tags']),
                    $material['author'],
                    $material['body'] ?? '',
                ]);
            }

            fclose($handle);
        }, 'teaching-materials.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUse($request);

        app(SaveTeachingMaterialAction::class)
            ->execute($this->validated($request), (int) $request->user()->id);

        return redirect()->route('academics.materials.index')->with('success', 'Material saved.');
    }

    public function update(Request $request, TeachingMaterial $material): RedirectResponse
    {
        $this->authorizeUse($request);

        // Ownership is enforced inside the action, so the rule lives with the
        // data rather than being restated here.
        app(SaveTeachingMaterialAction::class)
            ->execute($this->validated($request), (int) $request->user()->id, $material);

        return redirect()->route('academics.materials.index')->with('success', 'Material updated.');
    }

    /**
     * @return array{q: ?string, subject_id: ?int, tag: ?string, mine_for: ?int}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString() ?: null,
            'subject_id' => $request->integer('subject_id') ?: null,
            'tag' => $request->string('tag')->toString() ?: null,
            'mine_for' => $request->boolean('mine') ? (int) $request->user()->id : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'tags' => ['nullable'],
        ]);
    }

    private function authorizeUse(Request $request): void
    {
        abort_unless(
            $request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'),
            403,
        );
    }
}
