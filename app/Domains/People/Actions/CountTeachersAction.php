<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Teacher;

/**
 * How many teachers — the same two questions as `CountStudentsAction`, and the
 * same reason for asking them separately.
 *
 * `teachers.status` is `active` / `inactive` / `terminated`. A dashboard tile
 * headed **Teachers** was showing every row, so a teacher who left last year
 * was still being counted as staff.
 */
class CountTeachersAction
{
    /**
     * Teachers currently employed.
     */
    public function teaching(): int
    {
        return Teacher::query()->where('status', 'active')->count();
    }

    /**
     * Every teacher row, whatever became of them.
     */
    public function everEmployed(): int
    {
        return Teacher::query()->count();
    }
}
