<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\Courses\Components\Quran\Actions\SaveRevisionScheduleAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedQuranDashboardFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Aminath']);
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
        'created_by' => $admin->id,
    ]);
    app(EnrollUnifiedStudentInOfferingAction::class)->execute($student->id, $course->id, $offering->id);
    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $student->id,
        'surah_id' => $surah->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'academic_year_id' => $year->id,
    ]);

    return compact('admin', 'year', 'studentUser', 'student', 'teacher', 'teacherUser', 'surah', 'submission');
}

it('shows the student their submissions, progress and revision schedule', function () {
    $ctx = seedQuranDashboardFixture();
    app(ReviewRecitationAction::class)->execute($ctx['submission']->id, [
        'status' => 'needs_repeat',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
        'mistakes' => [['ayah_number' => 1, 'mistake_type' => 'missed_word']],
    ]);
    app(SaveRevisionScheduleAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'scheduled_date' => now()->addDay()->toDateString(),
        'frequency' => 'daily',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['studentUser'])
        ->get(route('learn.quran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Quran')
            ->has('submissions', 1)
            ->where('submissions.0.surah', 'Al-Fatihah')
            ->where('submissions.0.status', 'needs_repeat')
            ->where('submissions.0.mistake_count', 1)
            ->has('progress', 1)
            ->where('progress.0.status', 'needs_revision')
            ->has('schedules', 1)
            ->where('schedules.0.frequency', 'daily')
        );
});

it('lets a teacher work the review queue and rejects users who are neither teacher nor staff', function () {
    $ctx = seedQuranDashboardFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.recitations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Teach/RecitationQueue')
            ->has('rows', 1)
            ->where('rows.0.student.name', 'Aminath Ali')
            ->where('rows.0.status', 'submitted')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.recitations.review', $ctx['submission']->id), [
            'status' => 'passed',
            'note' => 'Excellent recitation.',
            'mistakes' => [],
        ])
        ->assertRedirect();

    $submission = QuranRecitationSubmission::query()->findOrFail($ctx['submission']->id);
    expect($submission->status->value)->toBe('passed')
        ->and((int) $submission->reviewed_by)->toBe((int) $ctx['teacherUser']->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.recitations', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()
        ->actingAs($stranger)
        ->get(route('teach.recitations'))
        ->assertForbidden();
});

it('aggregates oversight with letter and haraka names for staff', function () {
    $ctx = seedQuranDashboardFixture();
    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $fatha = ArabicHarakah::query()->where('key_name', 'fatha')->firstOrFail();
    $kasra = ArabicHarakah::query()->where('key_name', 'kasra')->firstOrFail();
    app(ReviewRecitationAction::class)->execute($ctx['submission']->id, [
        'status' => 'needs_repeat',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
        'mistakes' => [
            [
                'ayah_number' => 1,
                'expected_letter_id' => $baa->id,
                'expected_haraka_id' => $fatha->id,
                'predicted_letter_id' => $baa->id,
                'predicted_haraka_id' => $kasra->id,
            ],
        ],
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.quran.oversight'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/QuranOversight')
            ->where('total_submissions', 1)
            ->where('mistake_types.0.type', 'wrong_haraka')
            ->has('wrong_harakas', 1)
            ->where('wrong_harakas.0.display_name', 'Fatha')
            ->has('teacher_activity', 1)
            ->has('students', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.quran.oversight', ['format' => 'csv']))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('wrong_haraka')->toContain('Fatha');
});
