<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPostSeries;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL2a (§4.2): a blogger — a student or a full writer — writes a
 * post on *My blog*. Free to read, in the reader, through the one item
 * writer (SaveLibraryItemAction), so the text is sanitised and paged as
 * every item's is. Only their own post, and only while it is a draft or sent
 * back: a post waiting to be checked is out of their hands.
 *
 * A series is theirs, named on the form (new or existing); the post goes
 * last in it. The *own work* tick is the copyright declaration, which the
 * submission needs.
 */
class SaveBlogPostAction
{
    /**
     * @param  array{title: string, body?: ?string, language?: ?string, series_id?: ?int, new_series?: ?string, pinned?: bool, own_work?: bool}  $data
     */
    public function execute(int $userId, array $data, ?int $itemId = null, ?UploadedFile $cover = null): LibraryItem
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => __('blog.error_no_blog')]);
        }

        $item = null;
        if ($itemId !== null) {
            $item = LibraryItem::query()->findOrFail($itemId);
            if ((int) $item->writer_id !== (int) $profile->id || $item->content_type !== LibraryContentType::Post) {
                throw ValidationException::withMessages(['post' => __('blog.error_post_own')]);
            }
            if (! in_array($item->status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)) {
                throw ValidationException::withMessages(['post' => __('blog.error_post_not_editable')]);
            }
        }

        $series = $this->series($profile, $data);

        $saved = app(SaveLibraryItemAction::class)->execute([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'language' => in_array($data['language'] ?? null, ['en', 'dv', 'ar'], true) ? $data['language'] : 'en',
            'content_type' => LibraryContentType::Post->value,
            'access_type' => 'free_public',
            'declarations' => ['copyright' => (bool) ($data['own_work'] ?? false)],
            'created_by' => $userId,
            'writer_id' => $profile->id,
        ], $item, null, $cover);

        $position = $saved->series_position;
        if ($series === null) {
            $position = null;
        } elseif ((int) $saved->series_id !== (int) $series->id) {
            $position = (int) LibraryItem::query()->where('series_id', $series->id)->max('series_position') + 1;
        }
        $saved->fill(['series_id' => $series?->id, 'series_position' => $position, 'pinned' => (bool) ($data['pinned'] ?? false)])->save();

        return $saved->refresh();
    }

    private function series(WriterProfile $profile, array $data): ?LibraryPostSeries
    {
        $name = trim((string) ($data['new_series'] ?? ''));
        if ($name !== '') {
            $slug = Str::slug($name) ?: Str::lower(Str::random(8));

            return LibraryPostSeries::query()->firstOrCreate(
                ['writer_id' => $profile->id, 'slug' => $slug],
                ['title' => Str::limit($name, 120, ''), 'sort_order' => (int) LibraryPostSeries::query()->where('writer_id', $profile->id)->max('sort_order') + 1],
            );
        }
        if (empty($data['series_id'])) {
            return null;
        }
        $series = LibraryPostSeries::query()->whereKey((int) $data['series_id'])->where('writer_id', $profile->id)->first();
        if ($series === null) {
            throw ValidationException::withMessages(['series_id' => __('blog.error_series_own')]);
        }

        return $series;
    }
}
