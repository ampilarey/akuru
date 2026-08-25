<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * S2.1 — create rooms from distinct timetables.room strings.
 * Does not alter timetables or write room_id (S2.2). Idempotent.
 *
 * @return array{created: int, reused: int, skipped_blank: int}
 */
class SyncRoomsFromTimetableStringsAction
{
    /**
     * @return array{created: int, reused: int, skipped_blank: int}
     */
    public function execute(): array
    {
        $created = 0;
        $reused = 0;
        $skipped = 0;

        $rows = DB::table('timetables')
            ->select('room', 'room_arabic', 'room_dhivehi')
            ->orderBy('id')
            ->get();

        $seen = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->room ?? ''));

            if ($name === '') {
                $skipped++;

                continue;
            }

            if (isset($seen[$name])) {
                $this->fillEmptyTranslations($seen[$name], $row);

                continue;
            }

            $room = Room::query()->where('name', $name)->first();

            if ($room === null) {
                $room = Room::query()->create([
                    'name' => $name,
                    'name_arabic' => $this->nullableString($row->room_arabic ?? null),
                    'name_dhivehi' => $this->nullableString($row->room_dhivehi ?? null),
                    'type' => RoomType::Classroom,
                    'bookable' => true,
                    'active' => true,
                ]);
                $created++;
            } else {
                $this->fillEmptyTranslations($room, $row);
                $reused++;
            }

            $seen[$name] = $room;
        }

        return [
            'created' => $created,
            'reused' => $reused,
            'skipped_blank' => $skipped,
        ];
    }

    private function fillEmptyTranslations(Room $room, object $row): void
    {
        $dirty = false;

        if ($room->name_arabic === null) {
            $arabic = $this->nullableString($row->room_arabic ?? null);
            if ($arabic !== null) {
                $room->name_arabic = $arabic;
                $dirty = true;
            }
        }

        if ($room->name_dhivehi === null) {
            $dhivehi = $this->nullableString($row->room_dhivehi ?? null);
            if ($dhivehi !== null) {
                $room->name_dhivehi = $dhivehi;
                $dirty = true;
            }
        }

        if ($dirty) {
            $room->save();
        }
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
