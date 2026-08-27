<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Support\Contracts\HalaqaMilestoneWriter;

/**
 * F5-P3 final step: approve or reject through the writer contract, then
 * re-sync milestone-driven completion for the program so the engine
 * enrollment reflects the decision immediately (ADR-022 evaluator path).
 */
class DecideQuranMilestoneAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $milestoneId, int $decidedBy, bool $approved, ?string $note = null): array
    {
        $milestone = app(HalaqaMilestoneWriter::class)->decide($milestoneId, $decidedBy, $approved, $note);

        app(SyncHifzMilestoneProgressAction::class)->execute((int) $milestone['hifz_program_id']);

        return $milestone;
    }
}
