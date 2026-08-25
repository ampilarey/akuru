<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Models\Room;
use Illuminate\Validation\ValidationException;

class SaveRoomAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Room $room = null): Room
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $duplicate = Room::query()
            ->where('name', $name)
            ->when($room !== null, fn ($query) => $query->where('id', '!=', $room->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'A room with this name already exists.']);
        }

        $payload = [
            'name' => $name,
            'name_arabic' => $this->nullableString($data['name_arabic'] ?? null),
            'name_dhivehi' => $this->nullableString($data['name_dhivehi'] ?? null),
            'building' => $this->nullableString($data['building'] ?? null),
            'capacity' => isset($data['capacity']) && $data['capacity'] !== '' && $data['capacity'] !== null
                ? (int) $data['capacity']
                : null,
            'type' => RoomType::from((string) ($data['type'] ?? RoomType::Classroom->value)),
            'bookable' => array_key_exists('bookable', $data) ? (bool) $data['bookable'] : true,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($room === null) {
            return Room::query()->create($payload);
        }

        $room->fill($payload);
        $room->save();

        return $room->refresh();
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
