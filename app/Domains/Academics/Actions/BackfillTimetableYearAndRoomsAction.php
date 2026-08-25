<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

/**
 * S2.2 — stamp academic_year_id, room_id, valid_from/until on existing
 * timetable rows. Idempotent. Does not create rooms (S2.1).
 *
 * @return array{years: int, rooms: int, validity: int}
 */
class BackfillTimetableYearAndRoomsAction
{
    /**
     * @return array{years: int, rooms: int, validity: int}
     */
    public function execute(): array
    {
        $yearId = DB::table('academic_years')->where('status', 'active')->value('id')
            ?? DB::table('academic_years')->where('is_current', true)->value('id')
            ?? DB::table('academic_years')->orderBy('id')->value('id');

        $years = 0;
        if ($yearId !== null) {
            $years = DB::table('timetables')->whereNull('academic_year_id')->update([
                'academic_year_id' => (int) $yearId,
            ]);
        }

        $validity = DB::table('timetables')->whereNull('valid_from')->whereNotNull('start_date')->update([
            'valid_from' => DB::raw('start_date'),
        ]);
        $validity += DB::table('timetables')->whereNull('valid_until')->whereNotNull('end_date')->update([
            'valid_until' => DB::raw('end_date'),
        ]);

        $rooms = 0;
        foreach (DB::table('rooms')->get(['id', 'name']) as $room) {
            $rooms += DB::table('timetables')
                ->whereNull('room_id')
                ->where('room', $room->name)
                ->update(['room_id' => $room->id]);
        }

        return [
            'years' => $years,
            'rooms' => $rooms,
            'validity' => $validity,
        ];
    }
}
