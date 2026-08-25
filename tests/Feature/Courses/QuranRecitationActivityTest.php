<?php

use App\Domains\Courses\Actions\ResolveActivityDefinitionAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function seedRecitationSurah(): Surah
{
    $surah = Surah::query()->create([
        'index' => 112,
        'arabic_name' => 'الإخلاص',
        'english_name' => 'Al-Ikhlas',
        'transliteration' => 'Al-Ikhlas',
        'ayah_count' => 4,
        'revelation_place' => 'Meccan',
        'juz_start' => 30,
        'juz_end' => 30,
        'is_active' => true,
    ]);

    $mushaf = QuranMushaf::query()->create([
        'name' => 'Recitation mushaf',
        'source_type' => 'manual',
        'is_active' => true,
    ]);

    QuranAyah::query()->create([
        'quran_mushaf_id' => $mushaf->id,
        'surah_number' => 112,
        'ayah_number' => 1,
        'text_uthmani' => 'قُلْ هُوَ اللَّهُ أَحَدٌ',
        'text_simple' => 'قل هو الله احد',
    ]);
    QuranAyah::query()->create([
        'quran_mushaf_id' => $mushaf->id,
        'surah_number' => 112,
        'ayah_number' => 2,
        'text_uthmani' => 'اللَّهُ الصَّمَدُ',
        'text_simple' => 'الله الصمد',
    ]);

    return $surah;
}

it('stores recitation range on a teacher-marked activity and resolves the passage', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Hifz recitation',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $surah = seedRecitationSurah();

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Recite Al-Ikhlas 1-2',
        'pattern' => 'teacher_marked',
        'activity_type' => 'recitation',
        'data' => ['prompt' => 'Recite this range', 'submission_kind' => 'written'],
        'settings' => [
            'surah_id' => $surah->id,
            'ayah_start' => 1,
            'ayah_end' => 2,
            'teacher_review_required' => true,
        ],
    ]);

    expect($activity->pattern->value)->toBe('teacher_marked')
        ->and($activity->settings['surah_id'])->toBe($surah->id)
        ->and($activity->settings['ayah_start'])->toBe(1)
        ->and($activity->settings['ayah_end'])->toBe(2);

    $definition = app(ResolveActivityDefinitionAction::class)->execute($activity->id);
    expect($definition['quran']['surah']['english_name'])->toBe('Al-Ikhlas')
        ->and($definition['quran']['ayahs'])->toHaveCount(2)
        ->and($definition['quran']['ayahs'][0]['text_simple'])->toBe('قل هو الله احد');

    expect(fn () => app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bad surah',
        'pattern' => 'teacher_marked',
        'data' => ['prompt' => 'Recite', 'submission_kind' => 'written'],
        'settings' => ['surah_id' => 999999],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bad range',
        'pattern' => 'teacher_marked',
        'data' => ['prompt' => 'Recite', 'submission_kind' => 'written'],
        'settings' => ['surah_id' => $surah->id, 'ayah_start' => 1, 'ayah_end' => 99],
    ]))->toThrow(ValidationException::class);
});

it('lets catalog staff attach a recitation range from the activity builder', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Recitation builder',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $surah = seedRecitationSurah();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.courses.activities.store', $course->id), [
            'title' => 'Recite 112',
            'pattern' => 'teacher_marked',
            'activity_type' => 'recitation',
            'surah_id' => $surah->id,
            'ayah_start' => 1,
            'ayah_end' => 2,
            'data' => json_encode(['prompt' => 'Recite', 'submission_kind' => 'written']),
        ])
        ->assertRedirect(route('catalog.courses.activities.index', $course->id));
});
