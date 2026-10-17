<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryFollow;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL4 (§3.4): a signed-in reader follows a blog, stops, or says
 * whether new posts should come by email too. Writers cannot follow
 * themselves. The cached `followers_count` is recounted here and only here.
 */
class FollowBlogAction
{
    public function execute(int $userId, string $writerSlug, bool $follow, ?bool $email = null): ?LibraryFollow
    {
        $writer = WriterProfile::query()->where('slug', $writerSlug)->where('status', 'active')->first();
        if ($writer === null) {
            throw ValidationException::withMessages(['follow' => __('public.follow_error_no_blog')]);
        }
        if ((int) $writer->user_id === $userId) {
            throw ValidationException::withMessages(['follow' => __('public.follow_error_self')]);
        }

        return DB::transaction(function () use ($userId, $writer, $follow, $email) {
            $row = null;
            if ($follow) {
                $row = LibraryFollow::query()->firstOrCreate(['user_id' => $userId, 'writer_id' => $writer->id]);
                if ($email !== null) {
                    $row->fill(['notify_email' => $email])->save();
                }
            } else {
                LibraryFollow::query()->where('user_id', $userId)->where('writer_id', $writer->id)->delete();
            }
            $writer->forceFill(['followers_count' => LibraryFollow::query()->where('writer_id', $writer->id)->count()])->save();

            return $row;
        });
    }

    /**
     * What the follow box shows a reader on a blog's page or post.
     *
     * @return array{slug: string, following: bool, email: bool, count: int, own: bool, signed_in: bool}|null
     */
    public function state(?int $userId, string $writerSlug): ?array
    {
        $writer = WriterProfile::query()->where('slug', $writerSlug)->where('status', 'active')->first();
        if ($writer === null) {
            return null;
        }
        $row = $userId === null ? null : LibraryFollow::query()->where('user_id', $userId)->where('writer_id', $writer->id)->first();

        return [
            'slug' => (string) $writer->slug,
            'following' => $row !== null,
            'email' => (bool) $row?->notify_email,
            'count' => (int) $writer->followers_count,
            'own' => $userId !== null && (int) $writer->user_id === $userId,
            'signed_in' => $userId !== null,
        ];
    }

    /**
     * The blogs a reader follows, for *My Library*.
     *
     * @return list<array{display_name: string, blog_title: ?string, slug: string, email: bool, since: ?string}>
     */
    public function following(int $userId): array
    {
        return LibraryFollow::query()->with('writer')->where('user_id', $userId)->orderByDesc('id')->get()
            ->filter(fn (LibraryFollow $row) => $row->writer?->status === 'active')
            ->map(fn (LibraryFollow $row) => [
                'display_name' => (string) $row->writer->display_name,
                'blog_title' => $row->writer->blog_title,
                'slug' => (string) $row->writer->slug,
                'email' => (bool) $row->notify_email,
                'since' => $row->created_at?->toDateString(),
            ])->values()->all();
    }
}
