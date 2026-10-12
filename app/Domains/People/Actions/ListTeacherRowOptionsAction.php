<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Teachers by their `teachers` row, for a form that stores `teachers.id` —
 * a teaching plan (BACKLOG C22, BH1), which asked for a typed *Teacher id*.
 *
 * `ListClassTeacherOptionsAction` answers the same question by `users.id`,
 * which is what `classes.class_teacher_id` stores; a plan's `teacher_id` is the
 * row. Like `assignable()` there, a teacher who has left is not offered, but
 * the ids in `$keep` stay, so a `<select>` always holds its own value.
 */
class ListTeacherRowOptionsAction
{
    /**
     * @param  iterable<mixed>  $keep  teacher rows already chosen
     * @return Collection<int, array{id: int, name: string}>
     */
    public function execute(iterable $keep = []): Collection
    {
        $keep = collect($keep)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        return DB::table('teachers')
            ->where(function ($query) use ($keep): void {
                $query->where('status', 'active');
                if ($keep !== []) {
                    $query->orWhereIn('id', $keep);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ])
            ->values();
    }
}
