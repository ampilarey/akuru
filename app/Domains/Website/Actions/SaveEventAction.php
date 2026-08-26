<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveEventAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Event $event = null): Event
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location === '') {
            throw ValidationException::withMessages(['location' => 'Location is required.']);
        }

        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? $start;
        if ($start === null || $start === '') {
            throw ValidationException::withMessages(['start_date' => 'Start date is required.']);
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($title);
        }
        if ($slug === '') {
            $slug = 'event-'.Str::lower(Str::random(6));
        }

        $duplicate = Event::query()
            ->where('slug', $slug)
            ->when($event !== null, fn ($query) => $query->where('id', '!=', $event->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['slug' => 'An event with this slug already exists.']);
        }

        $description = trim((string) ($data['description'] ?? $data['short_description'] ?? $title));
        if ($description === '') {
            $description = $title;
        }

        $payload = [
            'title' => $title,
            'title_dv' => $this->nullableString($data['title_dv'] ?? null),
            'title_ar' => $this->nullableString($data['title_ar'] ?? null),
            'slug' => $slug,
            'description' => $description,
            'short_description' => $this->nullableString($data['short_description'] ?? Str::limit(strip_tags($description), 180)),
            'location' => $location,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $data['type'] ?? 'other',
            'status' => $data['status'] ?? 'draft',
            'registration_type' => $data['registration_type'] ?? 'required',
            'max_attendees' => $this->nullableInt($data['max_attendees'] ?? null),
            'min_attendees' => $this->nullableInt($data['min_attendees'] ?? null),
            'waitlist_enabled' => (bool) ($data['waitlist_enabled'] ?? false),
            'requires_parent_confirmation' => (bool) ($data['requires_parent_confirmation'] ?? false),
            'is_elective' => (bool) ($data['is_elective'] ?? false),
            'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : true,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'academic_year_id' => $this->nullableInt($data['academic_year_id'] ?? null),
            'registration_start' => $data['registration_start'] ?? null,
            'registration_deadline' => $data['registration_deadline'] ?? null,
        ];

        if ($event === null) {
            return Event::query()->create($payload);
        }

        $event->fill($payload);
        $event->save();

        return $event->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
