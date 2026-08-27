<?php

use App\Domains\Courses\Actions\PresentCourseLearningOutcomesAction;
use App\Domains\Courses\Actions\SaveCourseLearningOutcomesAction;
use App\Domains\Courses\Models\Course;
use App\Domains\HR\Models\Instructor;
use App\Domains\Website\Actions\ListCoursePageTestimonialsAction;
use App\Domains\Website\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function outcomesCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'W13 Outcomes Course',
        'slug' => 'w13-outcomes-'.fake()->unique()->numerify('###'),
        'status' => 'open',
        'body' => '<p>Course body copy.</p>',
        'cover_image' => 'courses/w13.jpg',
    ], $overrides));
}

it('omits the outcomes section when learning_outcomes is empty', function () {
    $course = outcomesCourse(['learning_outcomes' => null]);

    expect(app(PresentCourseLearningOutcomesAction::class)->execute($course->id, 'en'))->toBe([]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertDontSee('What you\'ll be able to do', false)
        ->assertDontSee('id="course-outcomes"', false);
});

it('renders localized learning outcomes above the description', function () {
    $course = outcomesCourse([
        'learning_outcomes' => [
            'en' => ['Read short surahs with tajweed', 'Write the Arabic alphabet'],
            'dv' => ['ތަޖުވީދުން ކިޔުން'],
            'ar' => ['قراءة مع التجويد'],
        ],
        'body' => '<p>Unique body marker W13-BODY</p>',
    ]);

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertSee('What you\'ll be able to do', false)
        ->assertSee('Read short surahs with tajweed', false)
        ->assertSee('Write the Arabic alphabet', false)
        ->getContent();

    expect(strpos($html, 'course-outcomes'))->toBeLessThan(strpos($html, 'W13-BODY'));

    expect(app(PresentCourseLearningOutcomesAction::class)->execute($course->id, 'dv'))
        ->toBe(['ތަޖުވީދުން ކިޔުން'])
        ->and(app(PresentCourseLearningOutcomesAction::class)->execute($course->id, 'fr'))
        ->toBe(['Read short surahs with tajweed', 'Write the Arabic alphabet']);
});

it('shows course testimonials and falls back to general quotes', function () {
    $own = outcomesCourse(['slug' => 'w13-own-testimonials']);
    $fallback = outcomesCourse(['slug' => 'w13-fallback-testimonials']);

    Testimonial::query()->create([
        'course_id' => null,
        'name' => 'General Parent',
        'role' => 'Parent',
        'quote' => 'W13 general institute quote',
        'order' => 1,
        'is_public' => true,
    ]);
    Testimonial::query()->create([
        'course_id' => $own->id,
        'name' => 'Fatima Course',
        'role' => 'Student',
        'quote' => 'W13 this course changed my recitation',
        'order' => 1,
        'is_public' => true,
    ]);

    expect(app(ListCoursePageTestimonialsAction::class)->execute($own->id)->pluck('quote')->all())
        ->toBe(['W13 this course changed my recitation'])
        ->and(app(ListCoursePageTestimonialsAction::class)->execute($fallback->id)->pluck('quote')->all())
        ->toBe(['W13 general institute quote']);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $own))
        ->assertOk()
        ->assertSee('W13 this course changed my recitation', false)
        ->assertDontSee('W13 general institute quote', false);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $fallback))
        ->assertOk()
        ->assertSee('W13 general institute quote', false);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee('W13 general institute quote', false)
        ->assertDontSee('W13 this course changed my recitation', false);
});

it('surfaces instructor qualifications prominently', function () {
    $course = outcomesCourse();
    $instructor = Instructor::query()->create([
        'name' => 'Ustadha W13 Shifa',
        'slug' => 'ustadha-w13-shifa',
        'qualification' => 'Ijazah in Quran, BA Islamic Studies (Madinah)',
        'specialization' => 'Tajweed for beginners',
        'bio' => 'Has taught at Akuru for several years.',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $course->instructors()->attach($instructor->id);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertSee('Qualifications', false)
        ->assertSee('Ijazah in Quran, BA Islamic Studies (Madinah)', false)
        ->assertSee('Tajweed for beginners', false)
        ->assertSee('Ustadha W13 Shifa', false);
});

it('saves trilingual learning outcomes from the public-site admin form', function () {
    $admin = actingPeopleAdmin();
    $course = outcomesCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('admin.courses.edit', $course))
        ->put(route('admin.courses.update', $course), [
            'course_category_id' => $course->course_category_id,
            'title' => $course->title,
            'slug' => $course->slug,
            'short_desc' => $course->short_desc,
            'body' => $course->body,
            'cover_image' => $course->cover_image,
            'language' => $course->language,
            'level' => $course->level,
            'status' => $course->status,
            'fee' => $course->fee,
            'seats' => $course->seats,
            'learning_outcomes_en' => "Recite with tajweed\nWrite isolated letters",
            'learning_outcomes_dv' => 'ކިޔުން',
            'learning_outcomes_ar' => '',
        ])
        ->assertRedirect(route('admin.courses.index'));

    $course->refresh();
    expect($course->learning_outcomes['en'])->toBe(['Recite with tajweed', 'Write isolated letters'])
        ->and($course->learning_outcomes['dv'])->toBe(['ކިޔުން'])
        ->and($course->learning_outcomes['ar'])->toBe([]);

    app(SaveCourseLearningOutcomesAction::class)->execute($course->id, ['en' => '', 'dv' => '', 'ar' => '']);
    expect($course->fresh()->learning_outcomes)->toBeNull();
});

it('does not import other-domain models from new W1.3 files', function () {
    $files = [
        app_path('Domains/Courses/Actions/PresentCourseLearningOutcomesAction.php'),
        app_path('Domains/Courses/Actions/SaveCourseLearningOutcomesAction.php'),
        app_path('Domains/Website/Actions/ListCoursePageTestimonialsAction.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\');
    }

    $website = file_get_contents(app_path('Domains/Website/Actions/ListCoursePageTestimonialsAction.php'));
    expect($website)->not->toMatch('/App\\\\Domains\\\\Courses\\\\Models\\\\/');
});
