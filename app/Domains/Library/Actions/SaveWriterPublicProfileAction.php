<?php

namespace App\Domains\Library\Actions;

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
        ])->save();

        return $profile->refresh();
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
