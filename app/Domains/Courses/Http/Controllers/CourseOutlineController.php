<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AttachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\DeleteContentBlockAction;
use App\Domains\Courses\Actions\DetachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\ListCourseOutlineAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ReorderContentBlocksAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Course;
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

    public function storeLesson(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveLessonAction::class)->execute($request->validate([
            'course_module_id' => ['required', 'integer', 'exists:course_modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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
            'direction' => ['nullable', 'string', 'in:ltr,rtl,auto'],
            'embed_url' => ['nullable', 'string', 'max:500'],
            'term' => ['nullable', 'string'],
            'definition' => ['nullable', 'string'],
            'entries_text' => ['nullable', 'string'],
            'lines_text' => ['nullable', 'string'],
            'cards_text' => ['nullable', 'string'],
            'quiz_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);
        $blockType = ContentBlockType::tryFrom($data['type']);
        if ($blockType?->isMedia()) {
            app(StoreMediaContentBlockAction::class)->execute([
                'lesson_id' => $data['lesson_id'],
                'type' => $data['type'],
                'file' => $request->file('file'),
                'embed_url' => $data['embed_url'] ?? null,
                'settings' => ['direction' => $data['direction'] ?? 'auto'],
                'created_by' => $request->user()?->id,
            ]);
        } else {
            app(SaveContentBlockAction::class)->execute([
                'lesson_id' => $data['lesson_id'],
                'type' => $data['type'],
                'data' => $this->blockDataFromRequest($data),
                'settings' => ['direction' => $data['direction'] ?? 'auto'],
                'created_by' => $request->user()?->id,
            ]);
        }

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Block saved.');
    }

    public function destroyBlock(Request $request, int $course, ContentBlock $block): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(DeleteContentBlockAction::class)->execute($block);

        return redirect()->route('catalog.courses.outline', $course)->with('success', 'Block deleted.');
    }

    public function reorderBlocks(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'block_ids' => ['required', 'array'],
            'block_ids.*' => ['integer'],
        ]);
        app(ReorderContentBlocksAction::class)->execute((int) $data['lesson_id'], $data['block_ids']);

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
