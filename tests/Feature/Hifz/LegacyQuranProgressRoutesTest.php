<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The legacy `/quran-progress` screens were guarded by `auth` alone.
 *
 * They sit in the same legacy block as `/students` and `/teachers` (#218), and
 * had the same shape of hole: **any signed-in account could write Quran
 * progress for any pupil** — a parent, or the pupil themselves.
 *
 * Rule 7 freezes Hifz to "namespace/route changes only". A route guard is
 * exactly a route change, and the freeze is explicitly scope discipline rather
 * than production-safety (ADR-021), so this is in scope. I declined it twice on
 * a stricter reading of that rule before re-reading it properly.
 *
 * Teachers are admitted here, unlike the `/students` block: recording a pupil's
 * memorisation is a teaching task, not an administrative one.
 */
function quranProgressUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);
    }

    return $user;
}

it('refuses a signed-in account with no role', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(quranProgressUser())
        ->get('/quran-progress')
        ->assertForbidden();
});

it('refuses a parent', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(quranProgressUser('parent'))
        ->get('/quran-progress')
        ->assertForbidden();
});

it('refuses a student writing their own memorisation record', function () {
    // The record is the school's judgement of the pupil, not the pupil's.
    $this->withoutLocalizationMiddleware()
        ->actingAs(quranProgressUser('student'))
        ->post('/quran-progress', [])
        ->assertForbidden();
});

it('refuses a parent posting a progress update', function () {
    $student = makeStudent();

    $this->withoutLocalizationMiddleware()
        ->actingAs(quranProgressUser('parent'))
        ->post('/quran-progress/'.$student->id.'/update', [])
        ->assertForbidden();
});

it('keeps anonymous visitors out', function () {
    $this->withoutLocalizationMiddleware()
        ->get('/quran-progress')
        ->assertRedirect();
});

it('admits the roles that actually record memorisation', function () {
    foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $role) {
        $this->withoutLocalizationMiddleware()
            ->actingAs(quranProgressUser($role))
            ->get('/quran-progress')
            ->assertOk();
    }
});
