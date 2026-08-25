<?php

namespace App\Domains\Hifz\Actions;

use App\Support\Contracts\HalaqaReferenceReader;

class ReadHalaqaReferenceAction implements HalaqaReferenceReader
{
    public function __construct(
        private readonly ListHifzProgramsAction $programs,
        private readonly ListHifzSessionsAction $sessions,
        private readonly ListHifzEnrollmentsAction $enrollments,
    ) {}

    public function listPrograms(): array
    {
        return $this->programs->execute();
    }

    public function findProgram(int $id): ?array
    {
        return $this->programs->find($id);
    }

    public function listSessions(int $programId): array
    {
        return $this->sessions->execute($programId);
    }

    public function findSession(int $id): ?array
    {
        return $this->sessions->find($id);
    }

    public function listEnrollments(int $programId): array
    {
        return $this->enrollments->execute($programId);
    }
}
