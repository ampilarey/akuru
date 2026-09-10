<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\TeachingMaterialFile;
use Illuminate\Validation\ValidationException;

/**
 * Take a file off a material.
 *
 * The link row goes; the stored file does not. Media owns its own lifecycle,
 * and a domain reaching across to delete another's bytes is how a file that is
 * still referenced somewhere else disappears (rule 3, rule 11).
 */
class RemoveMaterialFileAction
{
    public function execute(TeachingMaterialFile $file, int $actorUserId): void
    {
        if ((int) $file->material?->created_by !== $actorUserId) {
            throw ValidationException::withMessages([
                'file' => 'You can only remove files from materials you wrote.',
            ]);
        }

        $file->delete();
    }
}
