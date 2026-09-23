<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Models\Surah;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The activity builder rendered three of its refusals — pattern, data, title
 * — and swallowed the rest. `SaveActivityAction` also refuses under
 * `settings` (a recitation range past the end of its surah, an unknown surah
 * or letter) and `activity_type`, and those came back to a silent form with
 * the typed values still in the boxes, indistinguishable from a save. The
 * Qur'an A walk found it (STATUS §5fm). The controller is right, so half of
 * this guard reads the source: the form must render every error it gets.
 */
it('refuses a range past the end of the surah under a key the builder renders', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Recitation refusals',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $surah = Surah::query()->create([
        'index' => 112, 'arabic_name' => 'الإخلاص', 'english_name' => 'Al-Ikhlas', 'transliteration' => 'Al-Ikhlas',
        'ayah_count' => 4, 'revelation_place' => 'Meccan', 'juz_start' => 30, 'juz_end' => 30, 'is_active' => true,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('catalog.courses.activities.index', $course->id))
        ->post(route('catalog.courses.activities.store', $course->id), [
            'title' => 'Recite past the end',
            'pattern' => 'teacher_marked',
            'activity_type' => 'recitation',
            'surah_id' => $surah->id,
            'ayah_start' => 1,
            'ayah_end' => 99,
            'data' => json_encode(['prompt' => 'Recite', 'submission_kind' => 'written']),
        ])
        ->assertRedirect(route('catalog.courses.activities.index', $course->id))
        ->assertSessionHasErrors('settings');

    $source = file_get_contents(resource_path('js/Pages/Courses/Catalog/Activities.jsx'));

    expect($source)->toContain('<FormErrors errors={form.errors}')
        ->and($source)->not->toContain('{form.errors.pattern && <span');
});
