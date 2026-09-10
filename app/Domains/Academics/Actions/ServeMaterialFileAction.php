<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\TeachingMaterialFile;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Hand back a material's file, to someone entitled to it.
 *
 * This Action is the whole security surface of E13c, which is why the rule
 * lives here rather than in a controller: the files are private, and a route
 * that only checked "are you logged in" would hand every uploaded worksheet to
 * anyone with an account.
 *
 * Two ways in, and no others:
 *  - staff who can fill or manage registers see any material file, matching the
 *    library's existing staff-wide visibility (E13a);
 *  - a family sees a file only where the material was **sent home** (E13b) on a
 *    submitted register for a class their pupil is actually on.
 *
 * Attaching a material to a lesson is deliberately not enough. That is the same
 * distinction E13b draws, enforced again here so the download route cannot
 * become a way around it.
 */
class ServeMaterialFileAction
{
    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}
     */
    public function execute(TeachingMaterialFile $file, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);

        $canStaff = method_exists($user, 'can')
            && ($user->can('registers.fill') || $user->can('registers.manage'));

        abort_unless(
            $canStaff || $this->familyMayRead($file, (int) $user->getAuthIdentifier()),
            403,
        );

        $media = app(ReadPrivateMediaAction::class)->execute((int) $file->media_file_id);
        abort_if($media === null, 404);

        return $media;
    }

    private function familyMayRead(TeachingMaterialFile $file, int $userId): bool
    {
        $studentIds = $this->pupilsFor($userId);
        if ($studentIds === []) {
            return false;
        }

        $classIds = DB::table('class_student')
            ->whereIn('student_id', $studentIds)
            ->where('status', ClassStudentStatus::Active->value)
            ->pluck('class_id')
            ->all();

        if ($classIds === []) {
            return false;
        }

        return DB::table('lesson_log_material')
            ->join('lesson_logs', 'lesson_logs.id', '=', 'lesson_log_material.lesson_log_id')
            ->where('lesson_log_material.teaching_material_id', $file->teaching_material_id)
            ->where('lesson_log_material.for_homework', true)
            ->whereIn('lesson_logs.classroom_id', $classIds)
            // A draft register is the teacher's working copy, here as well as
            // in the homework list itself.
            ->whereIn('lesson_logs.status', [
                LessonLogStatus::Submitted->value,
                LessonLogStatus::Locked->value,
            ])
            ->exists();
    }

    /**
     * The pupils this account may read for: themselves, and any children they
     * are a registered guardian of.
     *
     * @return list<int>
     */
    private function pupilsFor(int $userId): array
    {
        $ids = [];

        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $ids[] = (int) $self['id'];
        }

        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $ids[] = (int) $child->id;
        }

        return array_values(array_unique($ids));
    }
}
