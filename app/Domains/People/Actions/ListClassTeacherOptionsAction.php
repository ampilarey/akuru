<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Users who have a teachers row. `classes.class_teacher_id` stores `users.id`.
 *
 * Two questions again, and one `execute()` used to answer both — which was
 * harmless only for as long as `teachers.status` could never change. Now that
 * `SyncTeacherRowStatusAction` moves it, they come apart:
 *
 *  - **naming** a class teacher already recorded, including somebody who has
 *    since left. The class directory's index and its CSV both look a name up
 *    this way, and dropping leavers would blank the column for a class whose
 *    teacher left rather than tell anyone why.
 *  - **choosing** one now, which should not offer somebody who has left.
 *
 * `assignable()` takes the ids already in use and keeps them, for the same
 * reason the lesson-material picker keeps what is already attached: a `<select>`
 * that does not contain its own current value silently changes that value on
 * the next save. A class whose teacher left keeps showing that teacher until
 * somebody deliberately picks another.
 */
class ListClassTeacherOptionsAction
{
    /**
     * Every teacher row, for naming whoever is recorded.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function everyone(): Collection
    {
        return $this->query()->get(['user_id', 'first_name', 'last_name'])
            ->map(fn (object $row) => [
                'id' => (int) $row->user_id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ])
            ->values();
    }

    /**
     * Teachers who may be assigned now, plus anyone already assigned.
     *
     * @param  iterable<mixed>  $keepUserIds  currently-recorded class teachers
     * @return Collection<int, array{id: int, name: string}>
     */
    public function assignable(iterable $keepUserIds = []): Collection
    {
        $keep = collect($keepUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->query()
            ->where(function ($query) use ($keep): void {
                $query->where('status', 'active');

                if ($keep !== []) {
                    $query->orWhereIn('user_id', $keep);
                }
            })
            ->get(['user_id', 'first_name', 'last_name'])
            ->map(fn (object $row) => [
                'id' => (int) $row->user_id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ])
            ->values();
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table('teachers')
            ->whereNotNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }
}
