<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\MeetingSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateMeetingSlotsAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<MeetingSlot>
     */
    public function execute(array $data, ?int $actorId = null): array
    {
        $minutes = (int) ($data['slot_minutes'] ?? 0);
        if ($minutes < 5 || $minutes > 120) {
            throw ValidationException::withMessages([
                'slot_minutes' => 'Slot length must be between 5 and 120 minutes.',
            ]);
        }

        $date = trim((string) ($data['date'] ?? ''));
        $start = $this->normalizeTime($data['start_time'] ?? null);
        $end = $this->normalizeTime($data['end_time'] ?? null);
        if ($date === '' || $start === null || $end === null) {
            throw ValidationException::withMessages(['start_time' => 'Date, start, and end time are required.']);
        }

        $cursor = Carbon::parse($date.' '.$start);
        $windowEnd = Carbon::parse($date.' '.$end);
        $windows = [];
        while ($cursor->copy()->addMinutes($minutes)->lte($windowEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($minutes);
            $windows[] = [
                'start_time' => $cursor->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
            ];
            $cursor = $slotEnd;
        }

        if ($windows === []) {
            throw ValidationException::withMessages([
                'slot_minutes' => 'That window is shorter than one slot.',
            ]);
        }

        return DB::transaction(function () use ($windows, $data, $actorId): array {
            $created = [];
            foreach ($windows as $window) {
                $created[] = app(SaveMeetingSlotAction::class)->execute(
                    array_merge($data, $window),
                    null,
                    $actorId,
                );
            }

            return $created;
        });
    }

    private function normalizeTime(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return strlen($trimmed) === 5 ? $trimmed.':00' : $trimmed;
    }
}
