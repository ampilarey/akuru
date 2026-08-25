<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListAudiencesAction;
use App\Domains\Courses\Actions\SaveAudienceAction;
use App\Domains\Courses\Models\Audience;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudienceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Taxonomy/Audiences', [
            'rows' => app(ListAudiencesAction::class)->execute()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveAudienceAction::class)->execute($this->validated($request));

        return redirect()->route('catalog.audiences.index')->with('success', 'Audience saved.');
    }

    public function update(Request $request, Audience $audience): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveAudienceAction::class)->execute($this->validated($request), $audience);

        return redirect()->route('catalog.audiences.index')->with('success', 'Audience updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $rows = app(ListAudiencesAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name_en', 'slug', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['name_en'], $row['slug'], $row['active'] ? '1' : '0']);
            }
            fclose($out);
        }, 'audiences.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_dv' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
