<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Models\QuranSessionRecord;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedQuranSessionFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Khadeeja']);
    $teacher = makeTeacherRow();
    $teacherUser = User::query()->findOrFail($teacher->user_id);
    $surah = Surah::query()->create([
        'index' => 1,
        'arabic_name' => 'الفاتحة',
        'english_name' => 'Al-Fatihah',
        'transliteration' => 'Al-Fatihah',
        'ayah_count' => 7,
        'revelation_place' => 'Meccan',
        'juz_start' => 1,
        'juz_end' => 1,
        'is_active' => true,
    ]);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Hifz engine course',
        'course_type' => 'hifz',
        'subject_id' => CourseSubject::query()->where('slug', 'hifz')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Hifz offering',
        'slug' => 'hifz-offering',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'academic_year_id' => $year->id,
        'created_by' => $admin->id,
    ]);
    $enrollment = app(EnrollUnifiedStudentInOfferingAction::class)
        ->execute($student->id, $course->id, $offering->id);
    $session = CourseOfferingSession::query()->create([
        'course_offering_id' => $offering->id,
        'academic_year_id' => $year->id,
        'title' => 'Morning halaqa',
        'session_type' => 'face_to_face',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'is_required' => false,
    ]);

    return compact('admin', 'year', 'student', 'teacher', 'teacherUser', 'surah', 'course', 'offering', 'enrollment', 'session');
}

it('renders the sheet, saves a three-lane record, and writes attendance through the single source', function () {
    $ctx = seedQuranSessionFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.quran-sessions.show', $ctx['session']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Teach/QuranSessionSheet')
            ->has('roster', 1)
            ->where('roster.0.student_name', 'Khadeeja Ali')
            ->where('roster.0.record', null)
            ->has('surahs', 1)
            ->has('options.lane_results', 5)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.store', $ctx['session']->id), [
            'course_enrollment_id' => $ctx['enrollment']->id,
            'attendance_status' => 'late',
            'new_from_surah_id' => $ctx['surah']->id,
            'new_from_ayah' => 1,
            'new_to_surah_id' => $ctx['surah']->id,
            'new_to_ayah' => 7,
            'new_result' => 'pass_with_notes',
            'new_score' => 85,
            'recent_revision_result' => 'pass',
            'old_revision_result' => 'repeat',
            'haraka_mistakes' => 2,
            'word_mistakes' => 1,
            'requires_supervisor_review' => true,
            'overall_status' => 'good',
            'teacher_note' => 'Solid but watch the harakas.',
        ])
        ->assertRedirect();

    $record = QuranSessionRecord::query()->firstOrFail();
    expect($record->new_result->value)->toBe('pass_with_notes')
        ->and((int) $record->mistake_count)->toBe(3)
        ->and((int) $record->academic_year_id)->toBe($ctx['year']->id)
        ->and((int) $record->student_id)->toBe($ctx['student']->id);

    // Attendance landed in attendance_records — the single source — not here.
    $attendance = AttendanceRecord::query()
        ->where('course_offering_session_id', $ctx['session']->id)
        ->where('enrollment_id', $ctx['enrollment']->id)
        ->firstOrFail();
    expect($attendance->status->value)->toBe('late');

    // Second save upserts the same row.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.store', $ctx['session']->id), [
            'course_enrollment_id' => $ctx['enrollment']->id,
            'new_result' => 'pass',
            'overall_status' => 'excellent',
        ])
        ->assertRedirect();
    expect(QuranSessionRecord::query()->count())->toBe(1)
        ->and(QuranSessionRecord::query()->first()->overall_status->value)->toBe('excellent');

    // Sheet now shows the record.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.quran-sessions.show', $ctx['session']->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster.0.record.new_result', 'pass')
            ->where('roster.0.status', 'late')
        );
});

it('rejects off-roster enrollments and invalid lane values', function () {
    $ctx = seedQuranSessionFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.store', $ctx['session']->id), [
            'course_enrollment_id' => 999999,
            'new_result' => 'pass',
        ])
        ->assertSessionHasErrors('course_enrollment_id');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.store', $ctx['session']->id), [
            'course_enrollment_id' => $ctx['enrollment']->id,
            'new_result' => 'bogus',
        ])
        ->assertSessionHasErrors('new_result');

    expect(QuranSessionRecord::query()->count())->toBe(0);
});

it('lets staff review a record and exports the sheet as CSV', function () {
    $ctx = seedQuranSessionFixture();
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.store', $ctx['session']->id), [
            'course_enrollment_id' => $ctx['enrollment']->id,
            'new_result' => 'repeat',
            'requires_supervisor_review' => true,
        ])
        ->assertRedirect();
    $record = QuranSessionRecord::query()->firstOrFail();

    // The teacher row alone does not carry courses.manage — review is staff-only.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.quran-sessions.records.review', $record->id), [
            'supervisor_note' => 'nope',
        ])
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->post(route('teach.quran-sessions.records.review', $record->id), [
            'supervisor_note' => 'Repeat next week with the same portion.',
        ])
        ->assertRedirect();

    $record->refresh();
    expect($record->reviewed_at)->not->toBeNull()
        ->and((bool) $record->requires_supervisor_review)->toBeFalse()
        ->and($record->supervisor_note)->toContain('Repeat next week');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.quran-sessions.show', ['session' => $ctx['session']->id, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
