<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\PromotionOutcome;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PromoteStudentsAction
{
    /**
     * @param  array<int, int>  $classMap  source class id → target class id
     * @param  array<int, string>  $overrides  student id → promote|repeat|leave|graduate
     * @return array{dry_run: bool, outcomes: list<array<string, mixed>>}
     */
    public function execute(
        int $sourceYearId,
        int $targetYearId,
        array $classMap,
        array $overrides,
        bool $dryRun,
        int $changedBy,
    ): array {
        if (! $dryRun && $this->missingDryRunConfirmation($sourceYearId, $targetYearId)) {
            throw new InvalidArgumentException('Dry-run is required before committing a promotion.');
        }

        $roster = ClassStudent::query()
            ->where('academic_year_id', $sourceYearId)
            ->where('status', ClassStudentStatus::Active->value)
            ->get();

        $outcomes = [];

        foreach ($roster as $row) {
            $outcome = PromotionOutcome::tryFrom((string) ($overrides[$row->student_id] ?? PromotionOutcome::Promote->value))
                ?? PromotionOutcome::Promote;

            $targetClassId = $classMap[$row->class_id] ?? null;

            $outcomes[] = [
                'student_id' => $row->student_id,
                'source_class_id' => $row->class_id,
                'outcome' => $outcome->value,
                'target_class_id' => $outcome === PromotionOutcome::Promote ? $targetClassId : (
                    $outcome === PromotionOutcome::Repeat ? $row->class_id : null
                ),
            ];
        }

        if ($dryRun) {
            return ['dry_run' => true, 'outcomes' => $outcomes];
        }

        DB::transaction(function () use ($roster, $overrides, $classMap, $targetYearId, $changedBy): void {
            foreach ($roster as $row) {
                $outcome = PromotionOutcome::tryFrom((string) ($overrides[$row->student_id] ?? PromotionOutcome::Promote->value))
                    ?? PromotionOutcome::Promote;

                match ($outcome) {
                    PromotionOutcome::Promote => $this->promote($row, $classMap, $changedBy),
                    PromotionOutcome::Repeat => $this->repeat($row),
                    PromotionOutcome::Leave => $this->leave($row, $changedBy),
                    PromotionOutcome::Graduate => $this->graduate($row, $changedBy),
                };
            }

            unset($targetYearId);
        });

        return ['dry_run' => false, 'outcomes' => $outcomes];
    }

    private function missingDryRunConfirmation(int $sourceYearId, int $targetYearId): bool
    {
        $key = "promotion-dry-run:{$sourceYearId}:{$targetYearId}";

        return ! cache()->pull($key);
    }

    public function rememberDryRun(int $sourceYearId, int $targetYearId): void
    {
        cache()->put("promotion-dry-run:{$sourceYearId}:{$targetYearId}", true, now()->addHour());
    }

    /**
     * @param  array<int, int>  $classMap
     */
    private function promote(ClassStudent $row, array $classMap, int $changedBy): void
    {
        $targetClassId = $classMap[$row->class_id] ?? null;
        if ($targetClassId === null) {
            throw new InvalidArgumentException("No target class mapped for class {$row->class_id}.");
        }

        $target = ClassRoom::query()->findOrFail($targetClassId);

        $row->forceFill([
            'status' => ClassStudentStatus::Promoted,
            'left_at' => now()->toDateString(),
        ])->save();

        app(AssignStudentToClassAction::class)->execute($target, $row->student_id);
        unset($changedBy);
    }

    private function repeat(ClassStudent $row): void
    {
        // Remain in the same class; roster row stays active.
        $row->touch();
    }

    private function leave(ClassStudent $row, int $changedBy): void
    {
        $row->forceFill([
            'status' => ClassStudentStatus::Left,
            'left_at' => now()->toDateString(),
        ])->save();

        DB::table('students')->where('id', $row->student_id)->update([
            'class_id' => null,
            'updated_at' => now(),
        ]);

        app(ChangeStudentStatusAction::class)->executeById(
            $row->student_id,
            'withdrawn',
            $changedBy,
            'promotion: leave',
        );
    }

    private function graduate(ClassStudent $row, int $changedBy): void
    {
        $row->forceFill([
            'status' => ClassStudentStatus::Promoted,
            'left_at' => now()->toDateString(),
        ])->save();

        DB::table('students')->where('id', $row->student_id)->update([
            'class_id' => null,
            'updated_at' => now(),
        ]);

        app(ChangeStudentStatusAction::class)->executeById(
            $row->student_id,
            'graduated',
            $changedBy,
            'promotion: graduate',
        );
    }
}
