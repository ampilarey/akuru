<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The database now enforces what the action promised.
 *
 * `EnsureTeacherRowAction` is idempotent because it checks for an existing row
 * first — a read-then-write that two concurrent requests can both pass. STATUS
 * recorded that `teachers.user_id` had a foreign key but no unique index, so
 * nothing underneath the application stopped a second profile.
 */
it('refuses a second teachers row for the same user', function () {
    $school = makeSchool();
    $user = User::factory()->create(['name' => 'Aminath Nasr', 'phone' => '7770130', 'address' => 'Malé']);

    $first = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    // Bypassing the action entirely is the point: this is the guarantee that
    // holds when the read-then-write races.
    expect(fn () => DB::table('teachers')->insert([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'teacher_id' => 'T-DUP-'.$user->id,
        'first_name' => 'Aminath', 'last_name' => 'Nasr',
        'date_of_birth' => '1990-01-01', 'gender' => 'female',
        'phone' => '', 'address' => '', 'email' => '',
        'qualification' => 'BA', 'specialization' => 'General',
        'joining_date' => now()->toDateString(), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(DB::table('teachers')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('teachers')->where('user_id', $user->id)->value('id'))->toBe($first->id);
});

it('still lets the action run twice without complaint', function () {
    $school = makeSchool();
    $user = User::factory()->create(['name' => 'Ibrahim Waheed', 'phone' => '7770131', 'address' => 'Malé']);

    $first = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);
    $second = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    expect($second->id)->toBe($first->id);
});
