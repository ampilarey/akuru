<?php

namespace App\Support\Contracts;

interface HalaqaReferenceReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPrograms(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findProgram(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(int $programId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findSession(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnrollments(int $programId): array;

    /**
     * Per-student memorisation milestones of a program (F2: mapped onto
     * engine completion rules by the Quran component).
     *
     * @return list<array<string, mixed>>
     */
    public function listMilestones(int $programId): array;

    /**
     * Per-student rows of one legacy session (F2: attendance backfill).
     *
     * @return list<array<string, mixed>>
     */
    public function listSessionRecords(int $sessionId): array;
}
