<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * L8 — what a writer shows readers on their author page (§8.7): display
 * name, bio, qualifications, expertise, portrait. Own profile only. The
 * portrait is PUBLIC media, deliberately: a face on an author page is
 * published, unlike the PDF original the reader protects.
 *
 * The address (`slug`) is set once from the display name and then kept, so
 * a link a reader saved keeps working after a rename. `slugFor()` is also
 * what approval uses to give a new writer an address.
 */
class SaveWriterPublicProfileAction
{
    /** How many of their own works a writer may pin at the top of the page (§8.7). */
    public const FEATURED_LIMIT = 3;

    /**
     * The addresses a writer may give readers, in the order the page shows
     * them. Keys, not free text: a link row is a fixed set of places, and a
     * key the page does not know is dropped rather than rendered.
     */
    public const LINK_KEYS = ['website', 'facebook', 'instagram', 'x', 'youtube', 'linkedin', 'telegram'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data, ?UploadedFile $photo = null): WriterProfile
    {
        $profile = WriterProfile::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }

        $name = trim((string) ($data['display_name'] ?? $profile->display_name));
        if ($name === '') {
            throw ValidationException::withMessages(['display_name' => 'A display name is required.']);
        }

        $photoId = $profile->photo_media_file_id;
        if ($photo !== null) {
            $stored = app(StorePublicMediaAction::class)->execute(
                $photo,
                $userId,
                ['image/jpeg', 'image/png', 'image/webp'],
                ['alt' => $name],
                'writer-portraits',
            );
            $photoId = $stored['id'];
        }

        $profile->fill([
            'display_name' => $name,
            'slug' => $profile->slug ?: self::slugFor($name),
            'bio' => $data['bio'] ?? $profile->bio,
            'qualifications' => $data['qualifications'] ?? $profile->qualifications,
            'expertise' => $data['expertise'] ?? $profile->expertise,
            'photo_media_file_id' => $photoId,
            'featured_item_ids' => array_key_exists('featured_item_ids', $data)
                ? $this->featured($profile, (array) $data['featured_item_ids'])
                : $profile->featured_item_ids,
            'social_links' => array_key_exists('social_links', $data)
                ? $this->links((array) $data['social_links'])
                : $profile->social_links,
        ])->save();

        return $profile->refresh();
    }

    /**
     * Up to three of the writer's **own published** works, in the order
     * given. Anything else — a draft, somebody else's item, an id that is
     * not theirs — is refused rather than dropped, so the writer learns
     * why the pin did not take.
     *
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function featured(WriterProfile $profile, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => is_numeric($id)))));
        if (count($ids) > self::FEATURED_LIMIT) {
            throw ValidationException::withMessages(['featured_item_ids' => 'Pick up to '.self::FEATURED_LIMIT.' works to feature.']);
        }
        if ($ids === []) {
            return [];
        }

        $own = LibraryItem::query()
            ->where('writer_id', $profile->id)
            ->where('status', 'published')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (count($own) !== count($ids)) {
            throw ValidationException::withMessages(['featured_item_ids' => 'Only your own published works can be featured.']);
        }

        return $ids;
    }

    /**
     * The known addresses only, each a full http(s) URL, empties dropped.
     *
     * @param  array<string, mixed>  $links
     * @return array<string, string>
     */
    private function links(array $links): array
    {
        $kept = [];
        foreach (self::LINK_KEYS as $key) {
            $url = trim((string) ($links[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            if (! preg_match('~^https?://~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false || mb_strlen($url) > 255) {
                throw ValidationException::withMessages(['social_links.'.$key => 'Give a full address starting with http:// or https://.']);
            }
            $kept[$key] = $url;
        }

        return $kept;
    }

    /** A free address made from the name; a clash gets a short suffix. */
    public static function slugFor(string $displayName): string
    {
        $base = Str::slug($displayName) ?: 'writer';
        $slug = $base;
        while (WriterProfile::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
