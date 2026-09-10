<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\TeachingMaterial;
use App\Domains\Academics\Models\TeachingMaterialFile;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Put a file on a material.
 *
 * Stored through Media's own action (rule 4: no storage SDK in domain logic),
 * and **privately** — a worksheet is not a public asset, and E13b can make it
 * reachable by a family without making it reachable by the internet.
 *
 * Only the author may add files, the same rule as editing the material itself.
 */
class AttachFileToMaterialAction
{
    /** What a teacher actually hands out: worksheets, slides, images, audio. */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/x-wav',
    ];

    /** 20 MB. Large enough for a scanned worksheet, small enough for cPanel. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function execute(TeachingMaterial $material, UploadedFile $file, int $actorUserId): TeachingMaterialFile
    {
        if ((int) $material->created_by !== $actorUserId) {
            throw ValidationException::withMessages([
                'file' => 'You can only add files to materials you wrote.',
            ]);
        }

        if (($file->getSize() ?: 0) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'That file is larger than 20 MB.',
            ]);
        }

        // Media raises its own ValidationException on a disallowed type, so the
        // list is enforced once rather than restated here.
        $stored = app(StorePrivateMediaAction::class)
            ->execute($file, $actorUserId, self::ALLOWED_MIMES);

        return TeachingMaterialFile::query()->create([
            'teaching_material_id' => $material->id,
            'media_file_id' => $stored['id'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime'],
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $actorUserId,
        ]);
    }
}
