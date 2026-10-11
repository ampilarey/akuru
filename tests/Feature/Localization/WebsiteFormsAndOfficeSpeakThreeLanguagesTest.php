<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\Website\Actions\ApproveDailyContentAction;
use App\Domains\Website\Actions\ComposeCourseFunnelReportAction;
use App\Domains\Website\Actions\CreateDailyContentBatchAction;
use App\Domains\Website\Actions\SaveDailyContentAction;
use App\Domains\Website\Actions\SaveNewsCategoryAction;
use App\Domains\Website\Actions\SaveNewsPostAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * The website's public forms and its office in Dhivehi and Arabic (BACKLOG
 * C21, slice WB1, STATUS §5rf).
 *
 * The pages read their books already (LT5, C9). What the server said on them
 * was English: the waiting list's refusal and the daily reminders'
 * subscription's three, the office's daily content (twelve, from the
 * maker–checker rule to a hadith without its grading), its news (five), and
 * the funnel report's six decisions.
 *
 * And the waiting list said itself three times: the form sits twice on a full
 * course's page and printed its own thanks under the layout's, while its
 * refusal sat under a form the page no longer shows once the course is not
 * full — said nowhere. The syllabus form, the contact form, an event's
 * registration and the reminders' subscription each said their thanks twice,
 * theirs and the layout's; the syllabus's refusals were said under a form
 * that had gone, or not at all; and the contact form's limit was English.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutLocalizationMiddleware();
});

function wb1ServerFiles(): array
{
    return [
        'app/Domains/Website/Actions/JoinCourseWaitlistAction.php',
        'app/Domains/Website/Actions/SaveDailyContentSubscriptionAction.php',
        'app/Domains/Website/Actions/ApproveDailyContentAction.php',
        'app/Domains/Website/Actions/CreateDailyContentBatchAction.php',
        'app/Domains/Website/Actions/SaveDailyContentAction.php',
        'app/Domains/Website/Actions/SaveNewsCategoryAction.php',
        'app/Domains/Website/Actions/SaveNewsPostAction.php',
        'app/Domains/Website/Actions/ComposeCourseFunnelReportAction.php',
        'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php',
        'app/Domains/Website/Http/Controllers/PublicSite/ContactController.php',
        'app/Domains/Website/Http/Controllers/PublicSite/EventController.php',
        'app/Domains/Website/Http/Controllers/PublicSite/DailySubscriptionController.php',
    ];
}

/** @return array<string, list<string>> */
function wb1Refusal(callable $call): array
{
    try {
        $call();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('leaves none of the forms\' and the office\'s English on the server', function () {
    foreach (wb1ServerFiles() as $file) {
        // An inquiry type's description is stored for the office's list, not said to anybody; and the
        // public book's keys are its English sentences, read through `__()`.
        $found = array_filter(refusalEnglishIn($file), fn (string $line) => ! str_contains($line, 'Interest form when a public course has no seats left.')
            && preg_match('/:\d+ public\./', $line) !== 1);
        expect(array_values($found))->toBe([], $file);
    }
});

it('keys every phrase the server files name in three languages', function () {
    $keys = refusalKeysIn(wb1ServerFiles());
    preg_match_all("/__\\('(public\\.[^']+)'/", implode("\n", array_map(fn ($f) => (string) file_get_contents(base_path($f)), wb1ServerFiles())), $sentences);
    $keys = array_values(array_unique([...$keys, ...$sentences[1]]));
    expect($keys)->toContain('admin.daily_error_own_item', 'admin.news_error_slug', 'admin.funnel_decision_keep', 'public.Send time must be HH:MM.', 'public.Waiting list is only open when the course is full.', 'public.Too many messages. Please wait :seconds seconds before trying again.');

    foreach ($keys as $key) {
        [$book, $phrase] = explode('.', $key, 2);
        expect((require base_path("resources/lang/en/{$book}.php"))[$phrase] ?? null)->not->toBeNull("{$key} has no English")
            ->and((require base_path("resources/lang/dv/{$book}.php"))[$phrase] ?? '')->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and((require base_path("resources/lang/ar/{$book}.php"))[$phrase] ?? '')->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('refuses a waiting list for a course with seats in Dhivehi, and says it once, on the page the course now shows', function () {
    app()->setLocale('dv');
    $course = Course::factory()->create(['status' => 'open']);
    $refusal = __('public.Waiting list is only open when the course is full.', [], 'dv');

    $this->from(route('public.courses.show', $course))->post(route('public.courses.waitlist', $course), ['name' => 'Aisha', 'phone' => '7771234'])
        ->assertRedirect(route('public.courses.show', $course))->assertSessionHas('error', $refusal);
    $page = $this->get(route('public.courses.show', $course))->assertOk()->getContent();
    expect(substr_count($page, e($refusal)))->toBe(1);

    // A form refused by Laravel's own rules, said the same way, in Dhivehi.
    $this->from(route('public.courses.show', $course))->post(route('public.courses.waitlist', $course), ['phone' => '7771234'])
        ->assertSessionHas('error', fn (string $message) => preg_match('/\p{Thaana}/u', $message) === 1);

    // The partial no longer prints the layout's thanks or a refusal of its own.
    $partial = (string) file_get_contents(resource_path('views/public/courses/_waitlist_form.blade.php'));
    expect($partial)->not->toContain("session('success')")->not->toContain("@error('course')");
});

it("says each website form's thanks once, and the syllabus's and the contact form's refusals in Dhivehi", function () {
    app()->setLocale('dv');
    $course = Course::factory()->create(['status' => 'open']);
    $show = route('public.courses.show', $course);
    $once = function (string $page, string $message) {
        expect(substr_count($this->get($page)->assertOk()->getContent(), e($message)))->toBe(1, $page);
    };

    // A syllabus the course does not have: said once, on the page that no longer offers it.
    $this->from($show)->post(route('public.courses.syllabus', $course), ['name' => 'Aisha', 'mobile' => '7771234'])
        ->assertSessionHas('error', __('public.A syllabus is not available for this course.', [], 'dv'));
    $once($show, __('public.A syllabus is not available for this course.', [], 'dv'));
    // The syllabus's thanks (the honeypot's answer is the same sentence's family, and needs no file).
    $this->from($show)->post(route('public.courses.syllabus', $course), ['website' => 'bot'])->assertSessionHas('success');
    $once($show, __('public.Syllabus is on its way.', [], 'dv'));

    $contact = route('public.contact.create');
    $this->from($contact)->post(route('public.contact.store'), ['name' => 'Aisha', 'message' => 'Salaam'])
        ->assertSessionHas('success', __('public.contact_message_sent', [], 'dv'));
    $once($contact, __('public.contact_message_sent', [], 'dv'));
    // The sixth message in ten minutes is refused, in Dhivehi.
    foreach (range(1, 5) as $i) {
        \Illuminate\Support\Facades\RateLimiter::hit('contact.127.0.0.1', 600);
    }
    $this->from($contact)->post(route('public.contact.store'), ['name' => 'Aisha', 'message' => 'Salaam'])->assertSessionHasErrors('message');
    expect(session('errors')->first('message'))->toMatch('/\p{Thaana}/u')->not->toContain('Too many');

    $event = \App\Domains\Website\Models\Event::create([
        'title' => 'WB1 Open Day', 'slug' => 'wb1-open-day', 'description' => 'WB1', 'location' => 'Malé', 'status' => 'published',
        'is_public' => true, 'registration_type' => 'required', 'start_date' => now()->addMonth(), 'end_date' => now()->addMonth(),
    ]);
    $page = route('public.events.show', $event->slug);
    $this->from($page)->post(route('public.events.register', $event), ['name' => 'Aisha', 'email' => 'wb1@example.test'])
        ->assertSessionHas('success', __('public.Registration submitted successfully!', [], 'dv'));
    $once($page, __('public.Registration submitted successfully!', [], 'dv'));

    $this->actingAs(User::factory()->create());
    $subscribe = route('public.daily.subscribe');
    $this->from($subscribe)->post(route('public.daily.subscribe.store'), ['channel' => 'email', 'content_types' => ['ayah'], 'language' => 'dv', 'send_time' => '06:00'])
        ->assertSessionHas('success', __('public.Subscription saved. You will only receive messages you opted into.', [], 'dv'));
    $once($subscribe, __('public.Subscription saved. You will only receive messages you opted into.', [], 'dv'));
});

it('refuses a daily reminders subscription in Dhivehi', function () {
    app()->setLocale('dv');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('public.daily.subscribe.store'), ['channel' => 'fax'])
        ->assertSessionHasErrors(['channel' => __('public.Choose SMS, email, or push.', [], 'dv')]);
    $this->actingAs($user)->post(route('public.daily.subscribe.store'), ['channel' => 'sms', 'content_types' => []])
        ->assertSessionHasErrors(['content_types' => __('public.Choose at least one content type.', [], 'dv')]);
});

it("says the office's daily content and news refusals in Dhivehi", function () {
    app()->setLocale('dv');
    $dv = require base_path('resources/lang/dv/admin.php');
    $maker = User::factory()->create();

    expect(wb1Refusal(fn () => app(SaveDailyContentAction::class)->execute(['content_type' => 'poem'], null, $maker->id)))
        ->toBe(['content_type' => [$dv['daily_error_type']]])
        ->and(wb1Refusal(fn () => app(SaveDailyContentAction::class)->execute(['content_type' => 'reminder', 'publish_date' => ''], null, $maker->id)))
        ->toBe(['publish_date' => [$dv['daily_error_date']]])
        ->and(wb1Refusal(fn () => app(CreateDailyContentBatchAction::class)->execute(['days' => 0, 'start_date' => '2026-11-01'], $maker->id)))
        ->toBe(['days' => [$dv['daily_error_batch_days']]]);

    $draft = DailyContent::query()->create([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-11-02',
        'status' => DailyContentStatus::Draft->value,
        'text_en' => 'Seek knowledge.',
        'created_by' => $maker->id,
    ]);
    expect(wb1Refusal(fn () => app(ApproveDailyContentAction::class)->execute($draft, $maker->id)))
        ->toBe(['approved_by' => [$dv['daily_error_own_item']]])
        ->and(wb1Refusal(fn () => app(ApproveDailyContentAction::class)->execute($draft, User::factory()->create()->id)))
        ->toBe(['text_en' => [$dv['daily_error_text_incomplete']]]);

    expect(wb1Refusal(fn () => app(SaveNewsCategoryAction::class)->execute(['name' => ''])))
        ->toBe(['name' => [$dv['news_error_category_name']]])
        ->and(wb1Refusal(fn () => app(SaveNewsPostAction::class)->execute(['title' => ''], null, $maker->id)))
        ->toBe(['title' => [$dv['news_error_title']]])
        ->and(wb1Refusal(fn () => app(SaveNewsPostAction::class)->execute(['title' => 'Eid', 'body' => ''], null, $maker->id)))
        ->toBe(['body' => [$dv['news_error_body']]]);
});

it("names the funnel's decisions in the page's language", function () {
    $funnel = app(ComposeCourseFunnelReportAction::class);
    app()->setLocale('dv');
    expect($funnel->decide([]))->toBe(__('admin.funnel_decision_no_data', [], 'dv'))
        ->and($funnel->decide(['course_view' => 1]))->toBe(__('admin.funnel_decision_keep', [], 'dv'));
    app()->setLocale('ar');
    expect($funnel->decide([]))->toMatch('/\p{Arabic}/u');
});
