<?php

use App\Domains\Courses\Actions\ComposeCourseConversionSignalsAction;
use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The public course catalogue and a course page in Dhivehi and Arabic
 * (BACKLOG C20, slice LT5a, STATUS §5ph).
 *
 * On `/dv/courses` and a course's page, a visitor read the filters and the
 * cards' words in English, a course's status, level and duration as codes or
 * English (`open`, `kids`, `8 weeks`), a course page's every heading, its
 * sidebar, its call to enrol, the Viber message, the sticky bar, the
 * syllabus and waiting-list forms and what they answered, and the seat
 * labels (`3 seats left`, `Limited seats`, `Full — join waiting list`),
 * which the home page's course cards show too.
 */
uses(RefreshDatabase::class);

function coursePageViews(): array
{
    return [
        'public/courses/index.blade.php',
        'public/courses/show.blade.php',
        'public/courses/_conversion_badges.blade.php',
        'public/courses/_price.blade.php',
        'public/courses/_syllabus_form.blade.php',
        'public/courses/_waitlist_form.blade.php',
        'public/courses/_whatsapp_link.blade.php',
    ];
}

function coursePageServerFiles(): array
{
    return [
        'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php',
        'app/Domains/Courses/Models/Course.php',
        'app/Domains/Courses/Actions/ComposeCourseConversionSignalsAction.php',
        'app/Domains/Website/Actions/ListCoursePageFaqsAction.php',
    ];
}

function coursePageBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/public.php");
}

function lt5aCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'LT5a Tajweed', 'slug' => 'lt5a-tajweed-'.uniqueFixtureSuffix(), 'status' => 'open',
        'level' => 'kids', 'language' => 'dv', 'seats' => 10, 'fee' => 800, 'duration_weeks' => 8,
        'start_date' => now()->addMonths(2)->setDay(5)->toDateString(),
        'enrollment_deadline' => now()->addDays(10)->toDateString(),
    ], $overrides));
}

function lt5aOccupy(Course $course, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('course_enrollments')->insert([
            'unified_student_id' => makeStudent(['first_name' => 'Seat', 'last_name' => 'Holder'.$i])->id,
            'course_id' => $course->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

it('prints no English of its own on the course pages', function () {
    $found = [];
    foreach (coursePageViews() as $view) {
        $path = resource_path('views/'.$view);
        // English, العربية and ދިވެހި name the course's language each in itself.
        foreach ([...bladeBareEnglish($path, ['MVR', 'Viber', 'English']), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('says every phrase of the course pages in Dhivehi and Arabic', function () {
    $en = coursePageBook('en');
    $gaps = [];
    foreach ([...array_map(fn ($view) => 'resources/views/'.$view, coursePageViews()), ...coursePageServerFiles()] as $file) {
        preg_match_all("/(?:__|trans_choice)\\(\\s*'public\\.((?:[^'\\\\]|\\\\.)+)'/", file_get_contents(base_path($file)), $matches);
        foreach (array_unique(array_map('stripslashes', $matches[1])) as $key) {
            foreach (['dv', 'ar'] as $locale) {
                $book = coursePageBook($locale);
                if (! array_key_exists($key, $en) || ! array_key_exists($key, $book) || $book[$key] === $en[$key]) {
                    $gaps[] = "{$locale} {$key} ({$file})";
                }
            }
        }
    }

    expect($gaps)->toBe([]);
});

it('writes the server\'s English nowhere on the course pages', function () {
    $found = [];
    foreach (coursePageServerFiles() as $file) {
        foreach (refusalEnglishIn($file) as $sentence) {
            $found[] = "{$file}: {$sentence}";
        }
    }

    // The `public` book is keyed by its English, so a key reads as a sentence; and the
    // waiting list's own note is the office's record of the lead, not the visitor's.
    expect(array_values(array_filter($found, fn ($line) => ! preg_match('/:\d+ (public\.|Waiting list for )/', $line))))->toBe([]);
});

it('asks the course page\'s questions in the page\'s language, its search card too', function () {
    foreach (['who', 'enroll', 'payment', 'refund', 'certificate', 'mode'] as $name) {
        foreach (['q', 'a'] as $part) {
            foreach (['dv', 'ar'] as $locale) {
                expect(coursePageBook($locale)["faq_{$name}_{$part}"] ?? null)->not->toBeNull()
                    ->not->toBe(coursePageBook('en')["faq_{$name}_{$part}"]);
            }
        }
    }

    $course = lt5aCourse();
    app()->setLocale('dv');
    $html = $this->withoutLocalizationMiddleware()->get(route('public.courses.show', $course->slug))->assertOk()->getContent();

    // The FAQPage JSON-LD carries the same questions as the page.
    expect($html)->toContain(e(coursePageBook('dv')['faq_who_q']))
        ->toContain(json_encode(coursePageBook('dv')['faq_who_q'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->not->toContain('Who is this course for?');
});

it('says the seat labels in the page\'s language', function () {
    $exact = lt5aCourse();
    lt5aOccupy($exact, 7);
    $limited = lt5aCourse(['seats' => 20]);
    lt5aOccupy($limited, 5);
    $full = lt5aCourse(['seats' => 1]);
    lt5aOccupy($full, 1);
    $action = app(ComposeCourseConversionSignalsAction::class);

    app()->setLocale('dv');
    expect($action->execute($exact->id)['seats_label'])->toBe('3 ޖާގަ ބާކީ')
        ->and($action->execute($limited->id)['seats_label'])->toBe(coursePageBook('dv')['Limited seats'])
        ->and($action->execute($full->id)['seats_label'])->toBe(coursePageBook('dv')['Full — join waiting list']);

    app()->setLocale('en');
    expect($action->execute($exact->id)['seats_label'])->toBe('3 seats left')
        ->and($action->execute($limited->id)['seats_label'])->toBe('Limited seats');
});

it('says a course\'s duration in the page\'s language, the English as it was', function () {
    app()->setLocale('ar');
    expect(lt5aCourse(['duration_weeks' => 3])->duration_text)->toBe('عدد الأسابيع: 3')
        ->and(lt5aCourse(['duration_weeks' => null])->duration_text)->toBe('مستمرة');

    app()->setLocale('en');
    expect(lt5aCourse(['duration_weeks' => 1])->duration_text)->toBe('1 week')
        ->and(lt5aCourse(['duration_weeks' => 3])->duration_text)->toBe('3 weeks')
        ->and(lt5aCourse(['duration_weeks' => 8])->duration_text)->toBe('2 months')
        ->and(lt5aCourse(['duration_weeks' => 4])->duration_text)->toBe('1 month')
        ->and(lt5aCourse(['duration_weeks' => null])->duration_text)->toBe('Ongoing');
});

it('serves the catalogue and a course page in Dhivehi and Arabic, with no English on them', function (string $locale) {
    $course = lt5aCourse();
    lt5aOccupy($course, 7);
    $book = coursePageBook($locale);
    app()->setLocale($locale);

    $list = $this->withoutLocalizationMiddleware()->get(route('public.courses.index'))->assertOk()->getContent();
    $page = $this->withoutLocalizationMiddleware()->get(route('public.courses.show', $course->slug))->assertOk()->getContent();

    expect($list)->toContain(e($book['Kids']))->toContain(e($book['Open']))->not->toContain('>Open<')
        ->and($page)->toContain(e($book['Course Information']))->toContain(e($book['Course Description']))
        ->toContain(e($book['Kids']))->toContain('ދިވެހި');
    foreach (['Course Information', 'Course Description', 'Enroll Now', 'Have questions? Chat with us.', 'Notify Me', 'seats left', 'days left', 'Starts '] as $english) {
        expect($page)->not->toContain(e($english));
    }
})->with(['dv', 'ar']);

it('answers the waiting list in the page\'s language', function () {
    $course = lt5aCourse(['seats' => 1]);
    lt5aOccupy($course, 1);

    $this->withHeader('Referer', url('/dv/courses/'.$course->slug))
        ->post(route('public.courses.waitlist', $course), ['name' => 'LT5a Waiter', 'phone' => '7770005'])
        ->assertRedirect()
        ->assertSessionHas('success', coursePageBook('dv')['You are on the waiting list. We will contact you if a seat opens.']);
});

it('refuses a syllabus the course does not have in the page\'s language', function () {
    $course = lt5aCourse();

    $this->withHeader('Referer', url('/ar/courses/'.$course->slug))
        ->post(route('public.courses.syllabus', $course), ['name' => 'LT5a Reader', 'mobile' => '7770006'])
        ->assertRedirect()
        ->assertSessionHasErrors(['course' => coursePageBook('ar')['A syllabus is not available for this course.']]);
});
