<?php

namespace App\Domains\People\Actions;

use App\Domains\Identity\Actions\CreateUserAction;
use Illuminate\Support\Facades\DB;

/**
 * A staff member's login account, made from the staff form (S1.4).
 *
 * Until 2026-09-22 the only screen that could create a teacher's *account* —
 * user, password, role, `teachers` row — was the pre-S1 Blade `/teachers`
 * form; the React staff form took an existing `user_id` and could not. This
 * is what that Blade form did, minus the `teacher_subject` links it also
 * wrote, which nothing has ever read.
 *
 * The account itself is created by Identity (it owns `User`); the `teachers`
 * row, which the timetable, registers and pickers key on, is People's and is
 * made here when the role is `teacher`.
 */
class CreateStaffAccountAction
{
    /** Roles a staff account may be created with. Parents and pupils are not staff. */
    public const ROLES = ['teacher', 'supervisor', 'headmaster', 'admin'];

    /**
     * @param  array{first_name: string, last_name?: ?string, email: string, password: string, phone?: ?string, role: string}  $data
     * @return int the new user's id
     */
    public function execute(array $data): int
    {
        $user = app(CreateUserAction::class)->execute(
            trim($data['first_name'].' '.($data['last_name'] ?? '')),
            $data['email'],
            $data['password'],
            $data['phone'] ?? null,
            $data['role'],
        );

        if ($data['role'] === 'teacher') {
            // Single-institute (ADR-001): the one school row.
            app(EnsureTeacherRowAction::class)->execute(
                (int) $user['id'],
                (int) DB::table('schools')->orderBy('id')->value('id'),
            );
        }

        return (int) $user['id'];
    }
}
