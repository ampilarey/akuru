<?php

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use App\Enums\Hifz\HifzEnrollmentStatus;
use Database\Seeders\ClassSeeder;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SurahSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * BACKLOG C16 slice N4 (STATUS §5nz). OWNER_ACTIONS 15, decided 2026-10-03:
 * "Add \"withdrawn\"". A Hifz enrolment can end — withdrawn, transferred or
 * completed — from the enrolment list, on a date with a note; the ended
 * pupil leaves every `active` count the same moment; a teacher cannot end
 * one; an ended one cannot end again; `paused` is not an ending.
 */
function endSeeded(): HifzEnrollment
{
    foreach ([RoleSeeder::class, SchoolSeeder::class, ClassSeeder::class, UserSeeder::class, SurahSeeder::class, HifzDemoSeeder::class] as $seeder) {
        test()->seed($seeder);
    }

    return HifzEnrollment::query()->where('status', 'active')->firstOrFail();
}

function endUser(string $email): User
{
    $user = User::query()->where('email', $email)->firstOrFail();
    $user->markEmailAsVerified();

    return $user;
}

it('lets the dean end an enrolment as withdrawn, and the list and the counts say so', function () {
    $enrollment = endSeeded();
    $program = HifzProgram::query()->findOrFail($enrollment->hifz_program_id);
    $dean = endUser('headmaster@akuru.edu.mv');
    $activeBefore = HifzEnrollment::query()->where('hifz_program_id', $program->id)->where('status', 'active')->count();

    $this->withoutLocalizationMiddleware()->actingAs($dean)->get(route('hifz.enrollments.index', $program))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Enrollments')
            ->where('can_update', true)->where('endings', ['withdrawn', 'transferred', 'completed'])->where('today', now()->toDateString())
            ->where('enrollments.data', fn ($rows) => collect($rows)->firstWhere('id', $enrollment->id)['ended'] === false)
            ->where('t.hifz_end_enrolment', 'End enrolment')->where('t.hifz_enrollment_status_withdrawn', 'withdrawn'));

    $this->withoutLocalizationMiddleware()->actingAs($dean)->from(route('hifz.enrollments.index', $program))
        ->post(route('hifz.enrollments.end', [$program, $enrollment]), ['status' => 'withdrawn', 'ended_at' => '2026-10-01', 'reason' => 'Family moved to Addu.'])
        ->assertRedirect(route('hifz.enrollments.index', $program))->assertSessionHas('success', 'Enrolment ended: withdrawn.');

    $enrollment->refresh();
    expect($enrollment->status)->toBe(HifzEnrollmentStatus::Withdrawn)
        ->and($enrollment->ended_at?->toDateString())->toBe('2026-10-01')
        ->and($enrollment->end_reason)->toBe('Family moved to Addu.')
        ->and((int) $enrollment->ended_by)->toBe($dean->id)
        ->and(HifzEnrollment::query()->where('hifz_program_id', $program->id)->where('status', 'active')->count())->toBe($activeBefore - 1);

    $this->withoutLocalizationMiddleware()->actingAs($dean)->get(route('hifz.enrollments.index', $program))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('enrollments.data', fn ($rows) => ($row = collect($rows)->firstWhere('id', $enrollment->id))['status'] === 'withdrawn' && $row['ended'] === true && $row['ended_at'] === '2026-10-01' && $row['end_reason'] === 'Family moved to Addu.'));

    // Ended once, it cannot end again; `paused` is not an ending; a bad date is refused.
    $this->withoutLocalizationMiddleware()->actingAs($dean)->post(route('hifz.enrollments.end', [$program, $enrollment]), ['status' => 'completed', 'ended_at' => '2026-10-02'])
        ->assertSessionHasErrors('status');
    // A second live enrolment, made for the test: the demo seed has one per programme.
    $other = HifzEnrollment::query()->create(['hifz_program_id' => $program->id, 'student_id' => makeStudent()->id, 'teacher_id' => $program->default_teacher_id, 'start_date' => now()->toDateString()]);
    $this->withoutLocalizationMiddleware()->actingAs($dean)->post(route('hifz.enrollments.end', [$program, $other]), ['status' => 'paused', 'ended_at' => '2026-10-02'])
        ->assertSessionHasErrors('status');
    $this->withoutLocalizationMiddleware()->actingAs($dean)->post(route('hifz.enrollments.end', [$program, $other]), ['status' => 'transferred', 'ended_at' => 'soon'])
        ->assertSessionHasErrors('ended_at');
    expect($other->fresh()->status)->toBe(HifzEnrollmentStatus::Active);

    // The phrases exist in Dhivehi and Arabic.
    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['hifz_enrollment_status_withdrawn', 'hifz_end_enrolment', 'hifz_end_withdrawn', 'hifz_flash_ended', 'hifz_end_intro'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('refuses a teacher, and an enrolment that is not the programme\'s', function () {
    $enrollment = endSeeded();
    $program = HifzProgram::query()->findOrFail($enrollment->hifz_program_id);

    $teacher = endUser('teacher@akuru.edu.mv');
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('hifz.enrollments.end', [$program, $enrollment]), ['status' => 'withdrawn', 'ended_at' => '2026-10-01'])
        ->assertForbidden();
    expect($enrollment->fresh()->status)->toBe(HifzEnrollmentStatus::Active);

    // Another programme's address with this enrolment's id is a 404, not a cross-programme write.
    $otherProgram = HifzProgram::query()->whereKeyNot($program->id)->first()
        ?? HifzProgram::query()->create(['name' => 'Other', 'status' => 'active', 'class_id' => $program->class_id, 'supervisor_id' => $program->supervisor_id, 'default_teacher_id' => $program->default_teacher_id]);
    $this->withoutLocalizationMiddleware()->actingAs(endUser('headmaster@akuru.edu.mv'))
        ->post(route('hifz.enrollments.end', [$otherProgram, $enrollment]), ['status' => 'withdrawn', 'ended_at' => '2026-10-01'])
        ->assertNotFound();
    expect($enrollment->fresh()->status)->toBe(HifzEnrollmentStatus::Active);
});
