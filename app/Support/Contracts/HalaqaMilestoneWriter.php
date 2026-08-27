<?php

namespace App\Support\Contracts;

/**
 * F5-P3: the milestone recommend → supervisor-review → approve/reject
 * workflow, driven from engine surfaces while hifz_milestones remains the
 * single milestone store (rule 11) until the ADR-025 retirement slice.
 * Implemented by Hifz as the data owner; the Courses side may not import
 * Hifz code, so writes cross the boundary here, next to
 * HalaqaReferenceReader.
 */
interface HalaqaMilestoneWriter
{
    /**
     * Create a pending milestone recommendation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the created milestone row
     */
    public function recommend(array $data): array;

    /**
     * Supervisor step: pending → supervisor_reviewed.
     *
     * @return array<string, mixed> the updated milestone row
     */
    public function review(int $milestoneId, int $reviewedBy, ?string $note = null): array;

    /**
     * Final step: pending/supervisor_reviewed → approved or rejected.
     *
     * @return array<string, mixed> the updated milestone row
     */
    public function decide(int $milestoneId, int $decidedBy, bool $approved, ?string $note = null): array;
}
