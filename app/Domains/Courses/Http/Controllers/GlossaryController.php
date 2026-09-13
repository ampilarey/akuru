<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\ListGlossaryItemsAction;
use App\Domains\Courses\Actions\SaveGlossaryItemAction;
use App\Domains\Courses\Actions\StoreGlossaryMediaAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\CourseLevel;
use App\Domains\Courses\Models\GlossaryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlossaryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Glossary', [
            'rows' => app(ListGlossaryItemsAction::class)->execute()->values(),
            'subjects' => app(ListCourseSubjectsAction::class)->execute()->values(),
            'levels' => CourseLevel::query()
                ->orderBy('sort_order')
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_dv', 'name_ar'])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveGlossaryItemAction::class)->execute(
            $this->validated($request) + $this->media($request) + ['created_by' => $request->user()?->id],
        );

        return redirect()->route('catalog.glossary.index')->with('success', 'Term saved.');
    }

    public function update(Request $request, GlossaryItem $glossaryItem): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveGlossaryItemAction::class)->execute(
            $this->validated($request) + $this->media($request),
            $glossaryItem,
        );

        return redirect()->route('catalog.glossary.index')->with('success', 'Term updated.');
    }

    public function destroy(Request $request, GlossaryItem $glossaryItem): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $glossaryItem->delete();

        return redirect()->route('catalog.glossary.index')->with('success', 'Term deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $rows = app(ListGlossaryItemsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'term', 'term_dv', 'term_ar', 'transliteration',
                'meaning_primary', 'meaning_secondary', 'meaning_dv', 'meaning_ar',
                'description', 'example_text', 'example_translation', 'tags',
                'subject_id', 'level_id',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['term'],
                    $row['term_dv'],
                    $row['term_ar'],
                    $row['transliteration'],
                    $row['meaning_primary'],
                    $row['meaning_secondary'],
                    $row['meaning_dv'],
                    $row['meaning_ar'],
                    $row['description'],
                    $row['example_text'],
                    $row['example_translation'],
                    implode(',', $row['tags'] ?? []),
                    $row['subject_id'],
                    $row['level_id'],
                ]);
            }
            fclose($out);
        }, 'glossary-items.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * SPEC §22's four media slots. They are uploaded rather than posted as ids:
     * the form had no file input at all, so the `exists:media_files,id` rules
     * below guarded a door nobody could reach.
     *
     * Sizes and mime types are §30's, enforced in `StoreGlossaryMediaAction`
     * against `ContentBlockType` — the outer `max:` here is only the bound the
     * request rules can know before the slot's kind is resolved.
     *
     * @return array<string, mixed>
     */
    private function media(Request $request): array
    {
        $request->validate([
            'audio_file' => ['nullable', 'file', 'max:'.(int) (ContentBlockType::Audio->maxBytes() / 1024)],
            'example_audio_file' => ['nullable', 'file', 'max:'.(int) (ContentBlockType::Audio->maxBytes() / 1024)],
            'image_file' => ['nullable', 'file', 'max:'.(int) (ContentBlockType::Image->maxBytes() / 1024)],
            'diagram_file' => ['nullable', 'file', 'max:'.(int) (ContentBlockType::Image->maxBytes() / 1024)],
            'clear_media' => ['nullable', 'array'],
            'clear_media.*' => ['string', Rule::in(array_keys(StoreGlossaryMediaAction::SLOTS))],
        ]);

        $media = ['clear_media' => $request->input('clear_media', [])];
        foreach (array_keys(StoreGlossaryMediaAction::SLOTS) as $slot) {
            $field = StoreGlossaryMediaAction::fileField($slot);
            if ($request->hasFile($field)) {
                $media[$field] = $request->file($field);
            }
        }

        return $media;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['nullable', 'integer', 'exists:course_subjects,id'],
            'category_id' => ['nullable', 'integer'],
            'term' => ['required', 'string', 'max:255'],
            'term_dv' => ['nullable', 'string', 'max:255'],
            'term_ar' => ['nullable', 'string', 'max:255'],
            'transliteration' => ['nullable', 'string', 'max:255'],
            'meaning_primary' => ['nullable', 'string'],
            'meaning_secondary' => ['nullable', 'string'],
            'meaning_dv' => ['nullable', 'string'],
            'meaning_ar' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'description_dv' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'example_text' => ['nullable', 'string'],
            'example_translation' => ['nullable', 'string'],
            'example_text_dv' => ['nullable', 'string'],
            'example_text_ar' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'level_id' => ['nullable', 'integer', 'exists:course_levels,id'],
            'audio_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'image_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'example_audio_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'diagram_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
        ]);
    }
}
