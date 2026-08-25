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
}
