<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Instructor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Creates or updates an instructor shown on the public website (C9 slice 3,
 * STATUS §5je). The slug is taken from the name once, at creation, so a
 * renamed instructor keeps the address the website already links to; the
 * portrait is public media on the `public` disk, as it always was.
 */
class SaveInstructorAction
{
    public const PHOTO_DIRECTORY = 'instructors';

    /**
     * @param  array<string, mixed>  $data  validated: name, bio, qualification, specialization, email, phone, is_active, sort_order
     */
    public function execute(?Instructor $instructor, array $data, ?UploadedFile $photo = null): Instructor
    {
        unset($data['photo']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($photo !== null) {
            $data['photo'] = $photo->store(self::PHOTO_DIRECTORY, 'public');
        }

        if ($instructor === null) {
            $data['slug'] = Str::slug($data['name']);

            return Instructor::query()->create($data);
        }

        $instructor->update($data);

        return $instructor->refresh();
    }
}
