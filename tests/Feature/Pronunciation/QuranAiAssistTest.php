<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Domains\Courses\Components\Quran\Actions\ListRecitationReviewQueueAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\DTOs\PronunciationPredictionResult;
use App\Domains\Pronunciation\Models\AiPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function seedQuranAiFixture(string $assignmentType = 'letter_haraka_practice'): array
{
    Queue::fake();
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Yusuf']);
    $teacher = makeTeacherRow();
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Hifz AI course',
        'course_type' => 'hifz',
        'subject_id' => CourseSubject::query()->where('slug', 'hifz')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Hifz AI offering',
        'slug' => 'hifz-ai-offering',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'pin_mode' => 'latest',
        'academic_year_id' => $year->id,
        'created_by' => $admin->id,
    ]);
    app(EnrollUnifiedStudentInOfferingAction::class)->execute($student->id, $course->id, $offering->id);

    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $fatha = ArabicHarakah::query()->where('key_name', 'fatha')->firstOrFail();
    $assignment = QuranHifzAssignment::query()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'course_id' => $course->id,
        'course_offering_id' => $offering->id,
        'academic_year_id' => $year->id,
        'expected_letter_id' => $baa->id,
        'expected_haraka_id' => $fatha->id,
        'assignment_type' => $assignmentType,
        'status' => 'assigned',
        'created_by' => $admin->id,
    ]);

    $audio = app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->create('drill.webm', 30, 'audio/webm'),
        $studentUser->id,
    );

    return compact('student', 'assignment', 'audio', 'baa', 'fatha');
}

function fakeQuranPredictor(string $letter, string $haraka): void
{
    app()->instance(PronunciationPredictionInterface::class, new class($letter, $haraka) implements PronunciationPredictionInterface
    {
        public function __construct(private string $letter, private string $haraka) {}

        public function predict(string $audioPath): PronunciationPredictionResult
        {
            return new PronunciationPredictionResult(
                success: true,
                predictedLetter: $this->letter,
                predictedHaraka: $this->haraka,
                letterConfidence: 0.93,
                harakaConfidence: 0.91,
                modelVersion: 'quran_test_v1',
                raw: ['fake' => true],
            );
        }
    });
}

it('checks a letter drill recitation through the same Pronunciation contract', function () {
    $ctx = seedQuranAiFixture();
    config()->set('ai.pronunciation_enabled', true);
    fakeQuranPredictor('baa', 'fatha');

    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'quran_hifz_assignment_id' => $ctx['assignment']->id,
        'audio_media_file_id' => $ctx['audio']['id'],
        'mode' => 'manual',
    ]);

    $prediction = AiPrediction::query()->firstOrFail();
    expect((int) $prediction->quran_recitation_submission_id)->toBe($submission->id)
        ->and($prediction->final_status)->toBe('correct')
        ->and($prediction->is_letter_match)->toBeTrue();

    // The teacher queue shows the AI opinion next to the submission.
    $queue = app(ListRecitationReviewQueueAction::class)->execute('submitted');
    $row = collect($queue['rows'])->firstWhere('id', $submission->id);
    expect($row['ai']['final_status'])->toBe('correct')
        ->and($row['ai']['letter'])->toBe('baa');
});

it('flags a wrong-letter drill for the teacher and stays silent with the flag off', function () {
    // Flag ON, wrong letter → the mismatch is visible to the teacher.
    $ctx = seedQuranAiFixture();
    config()->set('ai.pronunciation_enabled', true);
    fakeQuranPredictor('alif', 'fatha');
    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'quran_hifz_assignment_id' => $ctx['assignment']->id,
        'audio_media_file_id' => $ctx['audio']['id'],
        'mode' => 'manual',
    ]);
    expect(AiPrediction::query()->where('quran_recitation_submission_id', $submission->id)->firstOrFail()->final_status)
        ->toBe('wrong_letter');

    // Flag OFF → no prediction; the human flow is untouched (rule 8).
    config()->set('ai.pronunciation_enabled', false);
    $silent = app(SubmitRecitationAction::class)->execute([
        'student_id' => $ctx['student']->id,
        'audio_media_file_id' => $ctx['audio']['id'],
        'mode' => 'manual',
    ]);
    expect(AiPrediction::query()->where('quran_recitation_submission_id', $silent->id)->count())->toBe(0)
        ->and($silent->status->value)->toBe('submitted');
});
