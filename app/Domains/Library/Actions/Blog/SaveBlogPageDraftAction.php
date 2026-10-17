<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\Library\Support\BlogPage;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL3a (§5, D14): a blogger saves their page's new design as a
 * draft. The live page does not change: the whole set waits in
 * `pending_page` until the office approves it. Colours that do not read are
 * refused with the reason; a set already sent cannot be changed until it is
 * decided.
 *
 * BL3b: the banner and the picture are uploaded as **private** media into the
 * draft — the office sees them before anyone else can — and a draft keeps the
 * ones already chosen unless a new file or *remove* comes. A student's picture
 * needs the parent's photo choice; links on an under-18 student's blog need
 * the parent's `blog_links` consent.
 */
class SaveBlogPageDraftAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function execute(int $userId, array $input, ?UploadedFile $banner = null, ?UploadedFile $photo = null): WriterProfile
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['page' => __('blog.error_no_blog')]);
        }
        if ($profile->page_submitted_at !== null) {
            throw ValidationException::withMessages(['page' => __('blog.error_page_waiting')]);
        }

        $previous = is_array($profile->pending_page) ? $profile->pending_page : BlogPage::live($profile->only(BlogPage::LIVE_COLUMNS));
        $design = BlogPage::normalize($input);
        $rules = app(BlogPageRulesAction::class);
        if ($photo !== null && ! $rules->photoAllowed($profile)) {
            throw ValidationException::withMessages(['photo' => __('blog.error_photo_consent')]);
        }
        if ($design['links'] !== [] && ! $rules->linksAllowed($profile)) {
            throw ValidationException::withMessages(['links' => __('blog.error_links_consent')]);
        }
        $problems = BlogPage::problems($design);
        if ($problems !== []) {
            throw ValidationException::withMessages(['accent' => __('blog.error_contrast', [
                'ratio' => number_format($problems[0]['ratio'], 1),
                'needed' => number_format($problems[0]['needed'], 1),
            ])]);
        }

        // Checked before anything is stored, so a refused set leaves no files behind.
        $design['banner'] = $this->image($banner, $userId, ! empty($input['banner_remove']), $previous['banner'] ?? null);
        $design['photo'] = $this->image($photo, $userId, ! empty($input['photo_remove']) || ! $rules->photoAllowed($profile), $previous['photo'] ?? null);

        $profile->fill(['pending_page' => $design, 'page_decision_note' => null])->save();

        return $profile->refresh();
    }

    /** A new upload (private), none if removed, or the one already chosen. */
    private function image(?UploadedFile $file, int $userId, bool $remove, mixed $current): ?int
    {
        if ($file !== null) {
            return (int) app(StorePrivateMediaAction::class)->execute($file, $userId, self::IMAGE_TYPES, 4 * 1024 * 1024, 1600)['id'];
        }

        return $remove || ! is_int($current) ? null : $current;
    }
}
