<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\Courses\Components\Quran\Actions\SaveRevisionScheduleAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Courses\Components\Quran\Models\QuranMemorizationProgress;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function seedRecitationFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $student = makeStudent();
    $teacher = makeTeacherRow();
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
    $enrollment = app(EnrollUnifiedStudentInOfferingAction::class)
        ->execute($student->id, $course->id, $offering->id);

    return compact('admin', 'year', 'student', 'teacher', 'surah', 'course', 'offering', 'enrollment');
}

it('submits a recitation against the engine enrollment and validates the surah', function () {
    $ctx = seedRecitationFixture();

    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'academic_year_id' => $ctx['year']->id,
        'duration_seconds' => 90,
    ]);

    expect((int) $submission->course_enrollment_id)->toBe($ctx['enrollment']->id)
        ->and($submission->status->value)->toBe('submitted')
        ->and($submission->mode->value)->toBe('manual')
        ->and($submission->submitted_at)->not->toBeNull();

    expect(fn () => app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => 999,
    ]))->toThrow(ValidationException::class);
});

it('reviews haraka-strictly, deriving mistake types, and rolls memorization progress', function () {
    $ctx = seedRecitationFixture();
    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $alif = ArabicLetter::query()->where('key_name', 'alif')->firstOrFail();
    $fatha = ArabicHarakah::query()->where('key_name', 'fatha')->firstOrFail();
    $kasra = ArabicHarakah::query()->where('key_name', 'kasra')->firstOrFail();

    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'academic_year_id' => $ctx['year']->id,
    ]);

    $reviewed = app(ReviewRecitationAction::class)->execute($submission->id, [
        'status' => 'needs_repeat',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['admin']->id,
        'note' => 'Repeat with care on the harakas.',
        'mistakes' => [
            // بَ read as بِ — letter correct, haraka wrong (§52.2's own example).
            [
                'ayah_number' => 1,
                'word_position' => 2,
                'expected_letter_id' => $baa->id,
                'expected_haraka_id' => $fatha->id,
                'predicted_letter_id' => $baa->id,
                'predicted_haraka_id' => $kasra->id,
                'severity' => 'medium',
            ],
            [
                'ayah_number' => 2,
                'expected_letter_id' => $alif->id,
                'expected_haraka_id' => $fatha->id,
                'predicted_letter_id' => $baa->id,
                'predicted_haraka_id' => $fatha->id,
            ],
            ['ayah_number' => 3, 'mistake_type' => 'missed_word', 'severity' => 'major'],
        ],
    ]);

    expect($reviewed->status->value)->toBe('needs_repeat')
        ->and($reviewed->reviewed_at)->not->toBeNull()
        ->and($reviewed->mistakeMarks)->toHaveCount(3);

    $types = $reviewed->mistakeMarks()->orderBy('id')->get()
        ->map(fn ($mark) => $mark->mistake_type->value)
        ->all();
    expect($types)->toBe(['wrong_haraka', 'wrong_letter', 'missed_word']);

    $progress = QuranMemorizationProgress::query()
        ->where('student_id', $ctx['student']->id)
        ->where('surah_id', $ctx['surah']->id)
        ->firstOrFail();
    expect($progress->status->value)->toBe('needs_revision')
        ->and((int) $progress->mistake_count)->toBe(3);

    // A later clean pass updates the SAME row — the range is upserted, not duplicated.
    $second = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
    ]);
    app(ReviewRecitationAction::class)->execute($second->id, [
        'status' => 'passed',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['admin']->id,
    ]);

    expect(QuranMemorizationProgress::query()->count())->toBe(1);
    $progress->refresh();
    expect($progress->status->value)->toBe('passed')
        ->and((int) $progress->mistake_count)->toBe(0);
});

it('requires an explicit type when underivable and saves revision schedules', function () {
    $ctx = seedRecitationFixture();
    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'surah_id' => $ctx['surah']->id,
    ]);

    // No letter ids and no explicit type — nothing to derive from, loud failure.
    expect(fn () => app(ReviewRecitationAction::class)->execute($submission->id, [
        'status' => 'needs_repeat',
        'mistakes' => [['ayah_number' => 1]],
    ]))->toThrow(ValidationException::class);

    $schedule = app(SaveRevisionScheduleAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'teacher_id' => $ctx['teacher']->id,
        'academic_year_id' => $ctx['year']->id,
        'surah_id' => $ctx['surah']->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'scheduled_date' => now()->addDay()->toDateString(),
        'frequency' => 'weekly',
    ]);
    expect($schedule->status->value)->toBe('scheduled')
        ->and((int) $schedule->academic_year_id)->toBe($ctx['year']->id);

    expect(fn () => app(SaveRevisionScheduleAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'scheduled_date' => now()->toDateString(),
        'status' => 'bogus',
    ]))->toThrow(ValidationException::class);
});
