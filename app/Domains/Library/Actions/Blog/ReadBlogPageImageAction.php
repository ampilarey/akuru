<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\Blog\SaveBlogPageDraftAction as Draft;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * BLOG_PLAN BL3b: the banner or picture of a blog page's waiting design,
 * which is private media until approved — for the writer's own preview and
 * the office's check. The callers' routes decide who may ask; this reads
 * only the image the draft names, and only an image.
 */
class ReadBlogPageImageAction
{
    /**
     * @return array{contents: string, mime: string}|null
     */
    public function execute(WriterProfile $profile, string $kind): ?array
    {
        if (! in_array($kind, ['banner', 'photo'], true) || ! is_array($profile->pending_page)) {
            return null;
        }
        $id = $profile->pending_page[$kind] ?? null;
        $file = is_int($id) ? app(ReadPrivateMediaAction::class)->execute($id) : null;
        if ($file === null || ! in_array($file['mime'], Draft::IMAGE_TYPES, true)) {
            return null;
        }

        return ['contents' => $file['contents'], 'mime' => (string) $file['mime']];
    }
}
