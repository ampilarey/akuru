<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\TeachingMaterial;
use Illuminate\Validation\ValidationException;

/**
 * Set which materials a lesson used.
 *
 * Syncs rather than appends, so unticking one removes it — the register is a
 * record of what the lesson actually used, not an accumulation.
 *
 * The same edit rule as the register itself: your own lessons, unless you hold
 * `registers.manage`. Reusing that rule rather than inventing a second one
 * means a locked or someone else's register cannot be edited sideways through
 * the materials picker.
 */
class AttachMaterialsToLessonAction
{
    /**
     * @param  list<int>  $materialIds
     * @param  list<int>|null  $homeworkIds  which of them go home with the
     *                                       homework (E13b). Null leaves the
     *                                       existing choice alone, so a bundle
     *                                       that predates E13b cannot silently
     *                                       un-send everything (§5t).
     * @return list<int> the ids actually attached
     */
    public function execute(
        LessonLog $log,
        array $materialIds,
        int $actorUserId,
        bool $canManage = false,
        ?array $homeworkIds = null,
    ): array {
        if (! $canManage) {
            $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($actorUserId);
            if ($teacherId === null || (int) $log->teacher_id !== $teacherId) {
                throw ValidationException::withMessages([
                    'materials' => 'You can only change your own registers.',
                ]);
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $materialIds))));

        // Only ids that exist: a stale picker must not write a dangling link.
        $valid = $ids === []
            ? []
            : TeachingMaterial::query()->whereIn('id', $ids)->pluck('id')
                ->map(fn ($id): int => (int) $id)->all();

        $sendHome = $homeworkIds === null
            ? $this->currentlySentHome($log)
            : array_map('intval', $homeworkIds);

        $log->teachingMaterials()->sync(array_combine($valid, array_map(
            // Sending home a material that is not attached is not a state the
            // picker can produce, so the flag follows the attachment.
            fn (int $id): array => ['for_homework' => in_array($id, $sendHome, true)],
            $valid,
        )) ?: []);

        return $valid;
    }

    /**
     * @return list<int>
     */
    private function currentlySentHome(LessonLog $log): array
    {
        return $log->homeworkMaterials()
            ->pluck('teaching_materials.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
