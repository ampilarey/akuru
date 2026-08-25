<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzSession;

class ListHifzSessionsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $programId): array
    {
        return HifzSession::query()
            ->where('hifz_program_id', $programId)
            ->orderBy('session_date')
            ->orderBy('id')
            ->get()
            ->map(fn (HifzSession $session): array => $this->toArray($session))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $session = HifzSession::query()->find($id);

        return $session ? $this->toArray($session) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(HifzSession $session): array
    {
        return [
            'id' => $session->id,
            'hifz_program_id' => (int) $session->hifz_program_id,
            'title' => $session->title,
            'session_date' => $session->session_date?->toDateString(),
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'status' => $session->status?->value ?? $session->status,
        ];
    }
}
