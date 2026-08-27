<?php

use App\Domains\Identity\Models\User;
use App\Domains\Pronunciation\Actions\ActivateAiModelVersionAction;
use App\Domains\Pronunciation\Actions\DecideTrainingSampleAction;
use App\Domains\Pronunciation\Actions\ExportApprovedSamplesAction;
use App\Domains\Pronunciation\Actions\GetAiDatasetStatsAction;
use App\Domains\Pronunciation\Actions\SaveAiModelVersionAction;
use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\DTOs\PronunciationPredictionResult;
use App\Domains\Pronunciation\Models\AiModelVersion;
use App\Domains\Pronunciation\Models\AiModelVersionEvent;
use App\Domains\Pronunciation\Models\AiPrediction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function pronunciationIds(string $letter, string $haraka): array
{
    return [
        (int) DB::table('arabic_letters')->where('key_name', $letter)->value('id'),
        (int) DB::table('arabic_harakas')->where('key_name', $haraka)->value('id'),
    ];
}

function fakePredictor(string $letter, string $haraka, float $letterConf, float $harakaConf): void
{
    app()->instance(PronunciationPredictionInterface::class, new class($letter, $haraka, $letterConf, $harakaConf) implements PronunciationPredictionInterface
    {
        public function __construct(
            private string $letter,
            private string $haraka,
            private float $letterConf,
            private float $harakaConf,
        ) {}

        public function predict(string $audioPath): PronunciationPredictionResult
        {
            return new PronunciationPredictionResult(
                success: true,
                predictedLetter: $this->letter,
                predictedHaraka: $this->haraka,
                letterConfidence: $this->letterConf,
                harakaConfidence: $this->harakaConf,
                modelVersion: 'test_v1',
                raw: ['fake' => true],
            );
        }
    });
}

it('stores attempts for the human queue with AI off — the platform never depends on AI', function () {
    Queue::fake();
    $student = User::factory()->create();
    [$letterId, $harakaId] = pronunciationIds('baa', 'fatha');

    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $letterId,
            'expected_haraka_id' => $harakaId,
            'audio' => UploadedFile::fake()->create('attempt.webm', 40, 'audio/webm'),
        ])->assertSessionHasNoErrors();

    $attempt = ArabicPronunciationAttempt::query()->firstOrFail();
    expect($attempt->status)->toBe('submitted')
        ->and($attempt->teacher_review_required)->toBeTrue()
        ->and($attempt->audio_media_file_id)->not->toBeNull()
        ->and(AiPrediction::query()->count())->toBe(0);
});

it('checks attempts through the contract when the flag is on and routes by confidence', function () {
    Queue::fake();
    config()->set('ai.pronunciation_enabled', true);
    $student = User::factory()->create();
    [$baaId, $fathaId] = pronunciationIds('baa', 'fatha');
    [$alifId] = pronunciationIds('alif', 'fatha');

    // Confident and correct → spot-check only.
    fakePredictor('baa', 'fatha', 0.94, 0.88);
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $baaId,
            'expected_haraka_id' => $fathaId,
            'audio' => UploadedFile::fake()->create('a.webm', 40, 'audio/webm'),
        ]);
    $correct = ArabicPronunciationAttempt::query()->latest('id')->firstOrFail();
    $prediction = AiPrediction::query()->findOrFail($correct->ai_prediction_id);
    expect($prediction->final_status)->toBe('correct')
        ->and($prediction->is_letter_match)->toBeTrue()
        ->and($correct->teacher_review_required)->toBeFalse()
        ->and($correct->status)->toBe('ai_checked');

    // Confident but the wrong letter → back to the human queue.
    fakePredictor('baa', 'fatha', 0.94, 0.88);
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $alifId,
            'expected_haraka_id' => $fathaId,
            'audio' => UploadedFile::fake()->create('b.webm', 40, 'audio/webm'),
        ]);
    $wrong = ArabicPronunciationAttempt::query()->latest('id')->firstOrFail();
    expect(AiPrediction::query()->findOrFail($wrong->ai_prediction_id)->final_status)->toBe('wrong_letter')
        ->and($wrong->teacher_review_required)->toBeTrue();

    // Low confidence → human queue regardless of the labels.
    fakePredictor('baa', 'fatha', 0.50, 0.88);
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $baaId,
            'expected_haraka_id' => $fathaId,
            'audio' => UploadedFile::fake()->create('c.webm', 40, 'audio/webm'),
        ]);
    $low = ArabicPronunciationAttempt::query()->latest('id')->firstOrFail();
    expect(AiPrediction::query()->findOrFail($low->ai_prediction_id)->final_status)->toBe('low_confidence')
        ->and($low->teacher_review_required)->toBeTrue();
});

it('turns a teacher verdict into a pending training sample and guards the queues', function () {
    Queue::fake();
    $student = User::factory()->create();
    [$baaId, $fathaId] = pronunciationIds('baa', 'fatha');
    [$kasraLetterId, $kasraId] = pronunciationIds('baa', 'kasra');

    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $baaId,
            'expected_haraka_id' => $fathaId,
            'audio' => UploadedFile::fake()->create('d.webm', 40, 'audio/webm'),
        ]);
    $attempt = ArabicPronunciationAttempt::query()->firstOrFail();

    // Students cannot open the teacher queue.
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->get(route('teach.pronunciation'))->assertForbidden();

    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    // The teacher hears "bi", not "ba" — corrects the haraka.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('teach.pronunciation.review', $attempt->id), [
            'verified_letter_id' => $kasraLetterId,
            'verified_haraka_id' => $kasraId,
            'notes' => 'Clear kasra',
        ])->assertSessionHasNoErrors();

    $attempt->refresh();
    $sample = TrainingSample::query()->firstOrFail();
    expect($attempt->status)->toBe('teacher_reviewed')
        ->and($attempt->teacher_review_required)->toBeFalse()
        ->and($sample->status)->toBe('pending_review')
        ->and((int) $sample->verified_haraka_id)->toBe($kasraId);

    // A second review of the same attempt is refused.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('teach.pronunciation.review', $attempt->id), [
            'verified_letter_id' => $kasraLetterId,
            'verified_haraka_id' => $kasraId,
        ])->assertSessionHasErrors('attempt');
});

it('approves samples, exports a manifest, and audits model activation and rollback', function () {
    Storage::fake('local');
    Queue::fake();
    $student = User::factory()->create();
    [$baaId, $fathaId] = pronunciationIds('baa', 'fatha');
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.pronounce.store'), [
            'expected_letter_id' => $baaId,
            'expected_haraka_id' => $fathaId,
            'audio' => UploadedFile::fake()->create('e.webm', 40, 'audio/webm'),
        ]);
    $attempt = ArabicPronunciationAttempt::query()->firstOrFail();
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('teach.pronunciation.review', $attempt->id), [
            'verified_letter_id' => $baaId,
            'verified_haraka_id' => $fathaId,
        ]);
    $sample = TrainingSample::query()->firstOrFail();

    $admin = actingPeopleAdmin(['pronunciation.manage']);
    app(DecideTrainingSampleAction::class)->execute($sample->id, $admin->id, true);
    expect($sample->fresh()->status)->toBe('approved');
    expect(app(GetAiDatasetStatsAction::class)->execute()['cells'][0]['samples'])->toBe(1);

    $export = app(ExportApprovedSamplesAction::class)->execute();
    expect($export['count'])->toBe(1)
        ->and($sample->fresh()->status)->toBe('used_for_training');
    Storage::disk('local')->assertExists($export['manifest_path']);

    // Model shelf: v1 → activate; v2 → activate; roll back to v1. Audited.
    $v1 = app(SaveAiModelVersionAction::class)->execute(['version_name' => 'v1', 'model_path' => '/models/v1.h5'], $admin->id);
    $v2 = app(SaveAiModelVersionAction::class)->execute(['version_name' => 'v2', 'model_path' => '/models/v2.h5'], $admin->id);
    app(ActivateAiModelVersionAction::class)->execute($v1->id, $admin->id);
    app(ActivateAiModelVersionAction::class)->execute($v2->id, $admin->id);
    expect(AiModelVersion::query()->where('is_active', true)->count())->toBe(1)
        ->and($v2->fresh()->is_active)->toBeTrue();

    app(ActivateAiModelVersionAction::class)->execute($v1->id, $admin->id, isRollback: true);
    expect($v1->fresh()->is_active)->toBeTrue()
        ->and($v2->fresh()->is_active)->toBeFalse();
    expect(AiModelVersionEvent::query()->pluck('action')->countBy()->all())
        ->toBe(['registered' => 2, 'activated' => 2, 'rolled_back' => 1]);

    // Only pronunciation.manage may open the admin screen.
    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->get(route('admin.pronunciation.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.pronunciation.index'))->assertOk();
});
