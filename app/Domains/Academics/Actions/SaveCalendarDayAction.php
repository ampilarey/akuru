<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\CalendarDay;
use Illuminate\Validation\ValidationException;

class SaveCalendarDayAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CalendarDay $day = null): CalendarDay
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $date = (string) $data['date'];
        $yearId = (int) $data['academic_year_id'];

        $duplicate = CalendarDay::query()
            ->where('academic_year_id', $yearId)
            ->whereDate('date', $date)
            ->when($day !== null, fn ($query) => $query->where('id', '!=', $day->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'date' => 'That date already has a calendar entry for this year.',
            ]);
        }

        $payload = [
            'academic_year_id' => $yearId,
            'date' => $date,
            'type' => CalendarDayType::from((string) $data['type']),
            'title' => $title,
            'title_arabic' => $this->nullableString($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullableString($data['title_dhivehi'] ?? null),
            'affects_timetable' => array_key_exists('affects_timetable', $data)
                ? (bool) $data['affects_timetable']
                : true,
            'event_id' => isset($data['event_id']) && $data['event_id'] !== '' && $data['event_id'] !== null
                ? (int) $data['event_id']
                : null,
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];

        if ($day === null) {
            return CalendarDay::query()->create($payload);
        }

        $day->fill($payload);
        $day->save();

        return $day->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
