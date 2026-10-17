<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryPostSeries;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;

/**
 * BLOG_PLAN BL2a: the posts a blogger sees on *My blog*, and the posts a
 * class teacher has to check, oldest first.
 */
class ListBlogPostsAction
{
    /**
     * @return array{posts: list<array<string, mixed>>, series: list<array{id: int, title: string}>}
     */
    public function mine(int $userId): array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return ['posts' => [], 'series' => []];
        }
        $series = LibraryPostSeries::query()->where('writer_id', $profile->id)->orderBy('sort_order')->orderBy('id')->get();
        $posts = LibraryItem::query()
            ->where('writer_id', $profile->id)
            ->where('content_type', LibraryContentType::Post->value)
            ->orderByDesc('id')
            ->get();
        $notes = $this->latestNotes($posts->pluck('id')->all());

        return [
            'posts' => $posts->map(fn (LibraryItem $item) => $this->row($item, $series->firstWhere('id', $item->series_id)?->title) + [
                // BL2c: a published post's form holds its waiting edit, if it has one.
                'edit_title' => $item->hasPendingChange() ? $item->pending_title : $item->title,
                'body' => $item->hasPendingChange() ? $item->pending_body : $item->body,
                'language' => $item->language,
                'series_id' => $item->series_id,
                'own_work' => ! empty($item->declarations['copyright']),
                'editable' => in_array($item->status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)
                    || ($item->status === LibraryItemStatus::Published && $item->review_stage === null),
                'has_change' => $item->hasPendingChange(),
                'note' => $notes[$item->id] ?? null,
            ])->values()->all(),
            'series' => $series->map(fn (LibraryPostSeries $s) => ['id' => $s->id, 'title' => $s->title])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forTeacher(int $teacherUserId): array
    {
        $posts = LibraryItem::query()
            ->with('writer')
            // BL2c: a published post's edit waits for the teacher too.
            ->where(fn ($query) => $query->where('status', LibraryItemStatus::Submitted->value)
                ->orWhere(fn ($sub) => $sub->where('status', LibraryItemStatus::Published->value)->whereNotNull('pending_saved_at')))
            ->where('review_stage', 'teacher')
            ->where('teacher_user_id', $teacherUserId)
            ->orderBy('submitted_at')
            ->get();
        $series = LibraryPostSeries::query()->whereIn('id', $posts->pluck('series_id')->filter())->pluck('title', 'id');
        $resolve = app(ResolveStudentAgeForUserAction::class);
        $studentIds = $posts->mapWithKeys(fn (LibraryItem $item) => [$item->id => $item->writer?->user_id ? $resolve->execute((int) $item->writer->user_id)['student_id'] ?? null : null]);
        $names = app(ListStudentsByIdsAction::class)->execute($studentIds->filter()->values()->all())->pluck('name', 'id');

        return $posts->map(fn (LibraryItem $item) => $this->row($item, $item->series_id ? ($series[$item->series_id] ?? null) : null) + [
            'body' => $item->hasPendingChange() ? $item->pending_body : $item->body,
            'change' => $item->hasPendingChange(),
            'edit_title' => $item->pending_title,
            'pen_name' => $item->writer?->display_name,
            // The teacher knows whose post it is; readers see only the pen name.
            'student_name' => $names[$studentIds[$item->id]] ?? null,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function row(LibraryItem $item, ?string $seriesTitle): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'status' => $item->status?->value,
            'review_stage' => $item->review_stage,
            'series' => $seriesTitle,
            'series_position' => $item->series_position,
            'pinned' => (bool) $item->pinned,
            'submitted_at' => $item->submitted_at?->toDateTimeString(),
            'published_at' => $item->published_at?->toDateString(),
        ];
    }

    /**
     * The last reviewer's note on each post, for a post sent back or refused.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function latestNotes(array $ids): array
    {
        return LibraryItemReview::query()
            ->whereIn('library_item_id', $ids)
            ->whereNotNull('comment')
            ->where('decision', '!=', 'submitted')
            ->orderBy('id')
            ->get(['library_item_id', 'comment'])
            ->mapWithKeys(fn (LibraryItemReview $review) => [(int) $review->library_item_id => (string) $review->comment])
            ->all();
    }
}
