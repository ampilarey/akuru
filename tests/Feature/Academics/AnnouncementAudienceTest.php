<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListAnnouncementsForUserAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * E4 — the noticeboard reader.
 *
 * `announcements.target_audience` and `.target_classes` have been written by
 * the admin form since 2025 and read by nothing. The one apparent reader,
 * EnhancedDashboardController::getRecentAnnouncements(), returns the integer 0,
 * and families land on /portal/home and never reach that controller — so no
 * family has ever seen an announcement.
 *
 * makeNotice() lives in tests/Support/AcademicsTestHelpers.php.
 */
it('shows a school-wide notice to everyone', function () {
    makeNotice();
    $user = User::factory()->create();

    expect(app(ListAnnouncementsForUserAction::class)->execute((int) $user->id, ['parent']))
        ->toHaveCount(1);
});

it('treats a blank audience as the whole school, not nobody', function () {
    // The dangerous inversion: filtering on a blank target would silently hide
    // every row written before targeting was read by anything.
    makeNotice(['target_audience' => []]);
    $user = User::factory()->create();

    expect(app(ListAnnouncementsForUserAction::class)->execute((int) $user->id, []))
        ->toHaveCount(1);
});

it('honours an audience the notice was aimed at', function () {
    makeNotice(['target_audience' => ['parents'], 'title' => 'For parents']);
    makeNotice(['target_audience' => ['teachers'], 'title' => 'For teachers']);

    $reader = app(ListAnnouncementsForUserAction::class);
    $user = User::factory()->create();

    expect($reader->execute((int) $user->id, ['parent'])->pluck('title')->all())->toBe(['For parents'])
        ->and($reader->execute((int) $user->id, ['teacher'])->pluck('title')->all())->toBe(['For teachers']);
});

it('shows an all-audience notice alongside a targeted one', function () {
    makeNotice(['target_audience' => ['all'], 'title' => 'Everyone']);
    makeNotice(['target_audience' => ['students'], 'title' => 'Pupils only']);

    $titles = app(ListAnnouncementsForUserAction::class)
        ->execute((int) User::factory()->create()->id, ['parent'])
        ->pluck('title')->all();

    expect($titles)->toBe(['Everyone']);
});

it('hides an unpublished notice', function () {
    makeNotice(['is_published' => false]);

    expect(app(ListAnnouncementsForUserAction::class)->execute((int) User::factory()->create()->id, []))
        ->toBeEmpty();
});

it('hides a notice whose publish date has not arrived', function () {
    makeNotice(['publish_date' => now()->addWeek()->toDateString()]);

    expect(app(ListAnnouncementsForUserAction::class)->execute((int) User::factory()->create()->id, []))
        ->toBeEmpty();
});

it('hides an expired notice but keeps one expiring today', function () {
    makeNotice(['expiry_date' => now()->subDay()->toDateString(), 'title' => 'Gone']);
    makeNotice(['expiry_date' => now()->toDateString(), 'title' => 'Last day']);

    $titles = app(ListAnnouncementsForUserAction::class)
        ->execute((int) User::factory()->create()->id, [])
        ->pluck('title')->all();

    // A notice expiring today is still today's news.
    expect($titles)->toBe(['Last day']);
});

it('shows a class-targeted notice only to that class', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    makeNotice(['target_classes' => [$class->id], 'title' => 'Grade 5 A only']);

    $reader = app(ListAnnouncementsForUserAction::class);

    expect($reader->execute((int) $student->user_id, ['student'])->pluck('title')->all())
        ->toBe(['Grade 5 A only'])
        // A pupil on no roster is not in that class.
        ->and($reader->execute((int) makeStudent()->user_id, ['student']))
        ->toBeEmpty();
});

it('shows a class-targeted notice to the teacher of that class', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    app(\App\Domains\Academics\Actions\SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    makeNotice(['target_classes' => [$class->id], 'target_audience' => ['teachers']]);

    expect(app(ListAnnouncementsForUserAction::class)->execute((int) $teacher->user_id, ['teacher']))
        ->toHaveCount(1);
});

it('counts only urgent notices for the tile badge', function () {
    makeNotice(['priority' => 'medium']);
    makeNotice(['priority' => 'urgent']);
    makeNotice(['priority' => 'high']);

    // The badge has to be able to clear: there is no per-user read state, so a
    // badge counting everything would sit there forever.
    $summary = app(ListAnnouncementsForUserAction::class)
        ->summary((int) User::factory()->create()->id, []);

    expect($summary['total'])->toBe(3)
        ->and($summary['urgent'])->toBe(2);
});

it('falls back to English when a translation is blank', function () {
    makeNotice(['title' => 'Sports day', 'title_dhivehi' => '']);
    app()->setLocale('dv');

    // A blank Dhivehi title must not blank the notice.
    expect(app(ListAnnouncementsForUserAction::class)
        ->execute((int) User::factory()->create()->id, [])
        ->first()['title'])->toBe('Sports day');
});

it('uses the translation when the author supplied one', function () {
    makeNotice(['title' => 'Sports day', 'title_dhivehi' => 'ކުޅިވަރު ދުވަސް']);
    app()->setLocale('dv');

    expect(app(ListAnnouncementsForUserAction::class)
        ->execute((int) User::factory()->create()->id, [])
        ->first()['title'])->toBe('ކުޅިވަރު ދުވަސް');
});
