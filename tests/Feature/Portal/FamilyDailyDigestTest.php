<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\Portal\Actions\NotifyFamilyDailyDigestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * E22b — the family evening digest.
 *
 * Composes E1's next-school-day reader, E3a's homework list and E4's notices
 * into one message, delivered through E22a's notification centre. It computes
 * nothing of its own, so the digest cannot disagree with the pages it
 * summarises.
 */
function enableFamilyDigest(bool $on = true): void
{
    DB::table('settings')->updateOrInsert(
        ['key' => 'family_daily_digest'],
        ['value' => $on ? '1' : '0']
    );
}

/** A class that meets tomorrow, with one pupil on it. */
function seedDigestFamily(): array
{
    $tomorrow = now()->timezone(config('app.timezone'))->addDay();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    $subject = makeSubject();
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => strtolower($tomorrow->englishDayOfWeek),
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return compact('year', 'class', 'student', 'teacher', 'subject', 'tomorrow');
}

it('sends nothing while the setting is off', function () {
    $seed = seedDigestFamily();
    enableFamilyDigest(false);

    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(0)
        ->and(app(ListUserNotificationsAction::class)->unreadCount((int) $seed['student']->user_id))->toBe(0);
});

it('sends a pupil tomorrow lessons once the setting is on', function () {
    $seed = seedDigestFamily();
    enableFamilyDigest();

    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(1);

    $notice = app(ListUserNotificationsAction::class)->execute((int) $seed['student']->user_id)->first();

    expect($notice['title'])->toContain($seed['tomorrow']->toDateString())
        ->and($notice['message'])->toContain('1 lesson')
        ->and($notice['href'])->toBe('/portal/home');
});

it('sends the guardian too', function () {
    $seed = seedDigestFamily();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($seed['student'], $guardian, 'father');
    enableFamilyDigest();

    app(NotifyFamilyDailyDigestAction::class)->execute();

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $guardian->user_id))->toBe(1);
});

it('says nothing to a family with nothing to say', function () {
    // A pupil on no roster: no lessons, no homework, no notices.
    $student = makeStudent();
    enableFamilyDigest();

    // A digest that says "nothing" every evening teaches people to ignore it.
    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(0)
        ->and(app(ListUserNotificationsAction::class)->unreadCount((int) $student->user_id))->toBe(0);
});

it('still writes when there are no lessons but homework is outstanding', function () {
    $seed = seedDigestFamily();
    LessonLog::query()->create([
        'teacher_id' => $seed['teacher']->id,
        'subject_id' => $seed['subject']->id,
        'classroom_id' => $seed['class']->id,
        'academic_year_id' => $seed['year']->id,
        'date' => now()->toDateString(),
        'taught_summary' => 'Alphabet',
        'homework' => 'Read page 12',
        'homework_due_date' => now()->addDays(3)->toDateString(),
        'status' => LessonLogStatus::Submitted->value,
        'submitted_at' => now(),
    ]);
    enableFamilyDigest();

    app(NotifyFamilyDailyDigestAction::class)->execute();

    expect(app(ListUserNotificationsAction::class)->execute((int) $seed['student']->user_id)->first()['message'])
        ->toContain('Homework due soon: 1');
});

it('sends once per recipient per day', function () {
    seedDigestFamily();
    enableFamilyDigest();

    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(1)
        // A cron that fires twice must not message every family twice.
        ->and(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(0);
});

it('does not burn the daily slot on a family it skipped', function () {
    $student = makeStudent();
    enableFamilyDigest();

    // Skipped for having nothing to say...
    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(0);

    // ...so when something appears later the same day, the digest still lands.
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);
    $teacher = makeTeacherRow();
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => strtolower(now()->timezone(config('app.timezone'))->addDay()->englishDayOfWeek),
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    expect(app(NotifyFamilyDailyDigestAction::class)->execute())->toBe(1);
});

it('ignores accounts with no family at all', function () {
    seedDigestFamily();
    $stranger = User::factory()->create();
    enableFamilyDigest();

    app(NotifyFamilyDailyDigestAction::class)->execute();

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $stranger->id))->toBe(0);
});

it('is runnable as a command', function () {
    seedDigestFamily();
    enableFamilyDigest();

    $this->artisan('family:notify-daily-digest')
        ->expectsOutputToContain('Sent 1 family digest(s).')
        ->assertSuccessful();
});
