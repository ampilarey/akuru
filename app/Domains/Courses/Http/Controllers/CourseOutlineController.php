<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AttachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\DeleteContentBlockAction;
use App\Domains\Courses\Actions\DeleteCourseModuleAction;
use App\Domains\Courses\Actions\DetachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\DuplicateContentBlockAction;
use App\Domains\Courses\Actions\ListCourseOutlineAction;
use App\Domains\Courses\Actions\NormalizeBlockTextSettingsAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ReorderContentBlocksAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Courses\Models\Lesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseOutlineController extends Controller
{
    public function show(Request $request, int $course): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Outline', app(ListCourseOutlineAction::class)->execute($course));
    }

    public function storeModule(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        app(SaveCourseModuleAction::class)->execute($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]) + ['course_id' => $course, 'created_by' => $request->user()?->id]);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Module saved.');
    }

    /**
     * SPEC §12 "Delete draft modules if safe". There was no module delete at
     * all — blocks could be removed, the module holding them could not.
     */
    public function destroyModule(Request $request, int $course, CourseModule $module): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless((int) $module->course_id === $course, 404);

        app(DeleteCourseModuleAction::class)->execute($module);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Module deleted.');
    }

    public function storeLesson(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveLessonAction::class)->execute($request->validate([
            'course_module_id' => ['required', 'integer', 'exists:course_modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // SPEC §13 Lesson Management: "Set completion rules".
            'completion_rule' => ['nullable', 'string', 'max:40'],
        ]) + ['created_by' => $request->user()?->id]);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Lesson saved.');
    }

    public function storeBlock(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'type' => ['required', 'string', 'max:40'],
            'body' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'tone' => ['nullable', 'string'],
            // SPEC §15.3 makes these settings on every text-capable block
            // rather than separate block types. Only `direction` existed, so
            // a lesson could not say an Arabic passage was Arabic, could not
            // right-align a Thaana note, and could not ask for a Thaana face.
            'direction' => ['nullable', 'string', 'in:ltr,rtl,auto'],
            // "Text alignment using start/end" — logical only. `left`/`right`
            // are physical and silently wrong when the same block is read in
            // the other direction.
            'align' => ['nullable', 'string', 'in:start,end,center'],
            'language' => ['nullable', 'string', 'in:auto,en,dv,ar'],
            'font' => ['nullable', 'string', 'in:default,thaana,arabic'],
            'embed_url' => ['nullable', 'string', 'max:500'],
            'term' => ['nullable', 'string'],
            'definition' => ['nullable', 'string'],
            'entries_text' => ['nullable', 'string'],
            'lines_text' => ['nullable', 'string'],
            'cards_text' => ['nullable', 'string'],
            'quiz_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            // SPEC §28.1 carries "Required/optional status" into the lesson
            // revision snapshot, and §16 lets the author set it. The column
            // and the Action already handled it; nothing ever sent it.
            'is_required' => ['nullable', 'boolean'],
            // SPEC §30 sets the ceiling per kind of media. A single blanket
            // 50MB let an image be ten times its allowance and held a video to
            // a quarter of its own. The real limit depends on the block type,
            // which is validated just below, so the rule here is only the
            // outer bound — `StoreMediaContentBlockAction` applies the type's
            // own limit, and that is the number a caller actually hits.
            'file' => ['nullable', 'file', 'max:'.(int) (ContentBlockType::largestMaxBytes() / 1024)],
        ]);
        $blockType = ContentBlockType::tryFrom($data['type']);
        if ($blockType?->isMedia()) {
            app(StoreMediaContentBlockAction::class)->execute([
                'lesson_id' => $data['lesson_id'],
                'type' => $data['type'],
                'file' => $request->file('file'),
                'embed_url' => $data['embed_url'] ?? null,
                'settings' => $this->textSettings($data),
                'is_required' => (bool) ($data['is_required'] ?? false),
                'created_by' => $request->user()?->id,
            ]);
        } else {
            app(SaveContentBlockAction::class)->execute([
                'lesson_id' => $data['lesson_id'],
                'type' => $data['type'],
                'data' => $this->blockDataFromRequest($data),
                'settings' => $this->textSettings($data),
                'is_required' => (bool) ($data['is_required'] ?? false),
                'created_by' => $request->user()?->id,
            ]);
        }

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Block saved.');
    }

    public function destroyBlock(Request $request, int $course, ContentBlock $block): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless((int) $block->course_id === $course, 404);
        app(DeleteContentBlockAction::class)->execute($block);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Block deleted.');
    }

    /**
     * SPEC §16 "Duplicating blocks where safe" — there was no way to copy a
     * block at all, so a run of similar blocks had to be retyped.
     */
    public function duplicateBlock(Request $request, int $course, ContentBlock $block): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless((int) $block->course_id === $course, 404);

        app(DuplicateContentBlockAction::class)->execute($block);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Block duplicated.');
    }

    public function reorderBlocks(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'block_ids' => ['required', 'array'],
            'block_ids.*' => ['integer'],
        ]);
        // The lesson was only checked to exist. Without this a reorder posted
        // against course A could renumber a lesson belonging to course B, and
        // the redirect would send the author back to A showing no change.
        $lesson = Lesson::query()->findOrFail((int) $data['lesson_id']);
        abort_unless((int) $lesson->course_id === $course, 404);

        app(ReorderContentBlocksAction::class)->execute($lesson->id, $data['block_ids']);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Blocks reordered.');
    }

    public function publishLesson(Request $request, int $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(PublishLessonAction::class)->execute($lesson, $request->user()?->id);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Lesson published.');
    }

    public function attachGlossary(Request $request, int $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless((int) $lesson->course_id === $course, 404);
        $data = $request->validate([
            'glossary_item_id' => ['required', 'integer', 'exists:glossary_items,id'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        app(AttachLessonGlossaryItemAction::class)->execute(
            $lesson,
            (int) $data['glossary_item_id'],
            (bool) ($data['is_required'] ?? false),
        );

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Glossary term attached.');
    }

    public function detachGlossary(Request $request, int $course, Lesson $lesson, GlossaryItem $glossaryItem): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless((int) $lesson->course_id === $course, 404);
        app(DetachLessonGlossaryItemAction::class)->execute($lesson, $glossaryItem->id);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Glossary term removed.');
    }

    public function togglePreview(Request $request, int $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveLessonAction::class)->execute([
            'course_module_id' => $lesson->course_module_id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'description' => $lesson->description,
            'position' => $lesson->position,
            'is_preview' => ! $lesson->is_preview,
        ], $lesson);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Preview flag updated.');
    }

    /**
     * SPEC §13 Lesson Management: "Set completion rules."
     *
     * A dedicated endpoint rather than a general lesson edit, because there
     * is no lesson edit form — the outline builds lessons and toggles their
     * preview flag, and nothing else. Mirroring `togglePreview` keeps the one
     * shape the screen already has.
     */
    public function setCompletionRule(Request $request, int $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'completion_rule' => ['nullable', 'string', 'max:40'],
        ]);

        app(SaveLessonAction::class)->execute([
            'course_module_id' => $lesson->course_module_id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'description' => $lesson->description,
            'position' => $lesson->position,
            'is_preview' => $lesson->is_preview,
            'completion_rule' => $data['completion_rule'] ?? null,
        ], $lesson);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Completion rule updated.');
    }

    /**
     * SPEC §15.3's block text settings, merged over whatever the block
     * already carries.
     *
     * This used to assign a whole new `['direction' => ...]` array on every
     * save, so any other setting a block held was destroyed the next time
     * anyone touched it — which is why the four new settings merge rather
     * than replace.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function textSettings(array $data, array $existing = []): array
    {
        return app(NormalizeBlockTextSettingsAction::class)->execute($existing, [
            'direction' => $data['direction'] ?? 'auto',
            'align' => $data['align'] ?? null,
            'language' => $data['language'] ?? null,
            'font' => $data['font'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function blockDataFromRequest(array $data): array
    {
        return match ($data['type']) {
            'glossary', 'term' => [
                'term' => $data['term'] ?? '',
                'definition' => $data['definition'] ?? '',
                'entries' => $this->pairLines($data['entries_text'] ?? null, 'term', 'definition'),
            ],
            'dialogue' => [
                'lines' => $this->pairLines($data['lines_text'] ?? $data['body'] ?? null, 'speaker', 'text'),
            ],
            'flashcard' => [
                'cards' => $this->pairLines($data['cards_text'] ?? $data['body'] ?? null, 'front', 'back'),
            ],
            'quiz_embed' => [
                'quiz_id' => $data['quiz_id'] ?? null,
                'url' => $data['embed_url'] ?? '',
                'title' => $data['title'] ?? '',
            ],
            'assignment_embed' => [
                'assignment_id' => $data['assignment_id'] ?? null,
                'url' => $data['embed_url'] ?? '',
                'title' => $data['title'] ?? '',
            ],
            default => [
                'body' => $data['body'] ?? '',
                'html' => $data['html'] ?? $data['body'] ?? '',
                'tone' => $data['tone'] ?? 'note',
            ],
        };
    }

    /**
     * @return list<array<string, string>>
     */
    private function pairLines(?string $text, string $left, string $right): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $rows[] = [$left => $parts[0], $right => $parts[1]];
            }
        }

        return $rows;
    }
}
