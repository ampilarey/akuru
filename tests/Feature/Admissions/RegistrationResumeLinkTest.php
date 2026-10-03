<?php

use App\Domains\Admissions\Models\RegistrationFlow;
use App\Domains\Admissions\Notifications\RegistrationResumeLinkNotification;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * BACKLOG C16 slice N5 (STATUS §5oa). OWNER_ACTIONS 14, decided 2026-10-03:
 * build the resume link, short-lived and single-use, resuming the form
 * only. A family on the continue form asks for a link; it goes to their
 * verified contact; opening it brings their courses back and sends a fresh
 * code — it never signs them in; a second opening, a tampered token and an
 * expired link are all refused the same way.
 */
function resumeCourse(): Course
{
    $category = CourseCategory::query()->create(['name' => 'Tajweed', 'slug' => 'tajweed-'.uniqid(), 'order' => 1]);

    return Course::query()->create(['course_category_id' => $category->id, 'title' => 'Tajweed basics', 'slug' => 'tajweed-'.uniqid(), 'short_desc' => 's', 'body' => '<p>b</p>', 'cover_image' => null, 'language' => 'en', 'level' => 'all', 'status' => 'open', 'course_type' => 'general', 'workflow_status' => 'published']);
}

function resumeUser(string $type = 'mobile'): array
{
    $user = User::factory()->create();
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => $type, 'value' => $type === 'mobile' ? '+9607'.random_int(100000, 999999) : 'resume-'.uniqid().'@example.test', 'verified_at' => now()]);

    return [$user, $contact];
}

it('sends a single-use link by SMS from the continue form, and the link brings the courses back and asks for a code without signing in', function () {
    app()->instance(SmsSenderInterface::class, $sms = new LogSmsSender);
    [$user, $contact] = resumeUser('mobile');
    $course = resumeCourse();
    RateLimiter::clear('otp:send:'.$contact->id.':verify_contact');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':verify_contact');

    // The continue form offers the link.
    $this->withoutLocalizationMiddleware()->actingAs($user)->withSession(['pending_selected_course_ids' => [$course->id], 'pending_term_id' => null, 'checkout_flow' => 'adult'])
        ->get(route('courses.register.continue'))->assertOk()->assertSee('Send me a link');

    $this->withoutLocalizationMiddleware()->actingAs($user)->from(route('courses.register.continue'))
        ->withSession(['pending_selected_course_ids' => [$course->id], 'pending_term_id' => null, 'checkout_flow' => 'adult'])
        ->post(route('courses.register.resume-link'))
        ->assertRedirect(route('courses.register.continue'))
        ->assertSessionHas('success', fn ($message) => str_contains($message, $contact->value) && str_contains($message, 'works once'));

    $flow = RegistrationFlow::query()->where('user_id', $user->id)->sole();
    expect($flow->status)->toBe('selecting_students')
        ->and($flow->payload['course_ids'])->toBe([$course->id])
        ->and($flow->payload['checkout_flow'])->toBe('adult')
        ->and($flow->resume_token_hash)->toHaveLength(64)
        ->and($flow->resumed_at)->toBeNull()
        ->and($flow->expires_at->diffInHours(now(), true))->toBeLessThanOrEqual(24.1)
        ->and($flow->toArray())->not->toHaveKey('resume_token_hash');

    $sent = $sms->sent[0] ?? null;
    expect($sent)->not->toBeNull()->and($sent['phone'])->toBe($contact->value)->and($sent['body'])->toContain('works once');
    preg_match('/https?:\/\/\S+/', $sent['body'], $m);
    $url = $m[0];
    expect($url)->toContain('flow='.$flow->uuid)->toContain('t=')->not->toContain($flow->resume_token_hash);

    // A stranger with the link, in a fresh browser: the courses come back, a
    // code is sent to the contact, nobody is signed in.
    app('auth')->forgetGuards();
    $response = $this->withoutLocalizationMiddleware()->get($url);
    $response->assertRedirect(route('courses.register.otp'))->assertSessionHas('info');
    expect(session('pending_selected_course_ids'))->toBe([$course->id])
        ->and((int) session('pending_user_id'))->toBe($user->id)
        ->and((int) session('pending_contact_id'))->toBe($contact->id)
        ->and(session('otp_verified_user_id'))->toBeNull()
        ->and(auth()->check())->toBeFalse()
        ->and($flow->fresh()->resumed_at)->not->toBeNull()
        ->and(count($sms->sent))->toBe(2);

    // Spent: the same link again is refused, and so is a tampered token.
    $this->withoutLocalizationMiddleware()->get($url)
        ->assertRedirect(route('public.courses.index'))->assertSessionHas('error', fn ($message) => str_contains($message, 'already been used or has expired'));
    $tampered = preg_replace('/t=\w+/', 't='.str_repeat('x', 40), $url);
    $this->withoutLocalizationMiddleware()->get($tampered)->assertRedirect(route('public.courses.index'))->assertSessionHas('error');
});

it('sends by email when that is the verified contact, refuses an expired link, and needs a verified session to ask', function () {
    Notification::fake();
    [$user, $contact] = resumeUser('email');
    $course = resumeCourse();

    $this->withoutLocalizationMiddleware()->actingAs($user)->from(route('courses.register.continue'))
        ->withSession(['pending_selected_course_ids' => [$course->id]])
        ->post(route('courses.register.resume-link'))->assertRedirect(route('courses.register.continue'))->assertSessionHas('success');
    Notification::assertSentOnDemand(RegistrationResumeLinkNotification::class, fn ($notification, $channels, $notifiable) => in_array('mail', $channels, true)
        && $notifiable->routes['mail'] === $contact->value && str_contains($notification->url, 'flow=') && $notification->courses === ['Tajweed basics']);

    // Expired: refused.
    $flow = RegistrationFlow::query()->where('user_id', $user->id)->sole();
    $flow->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->withoutLocalizationMiddleware()->get(route('courses.register.resume', ['flow' => $flow->uuid, 't' => 'whatever']))
        ->assertRedirect(route('public.courses.index'))->assertSessionHas('error');

    // No verified session, no link.
    app('auth')->forgetGuards();
    $this->withoutLocalizationMiddleware()->post(route('courses.register.resume-link'))->assertRedirect(route('public.courses.index'))->assertSessionHas('error');

    // A signed-in person with nothing chosen is told so rather than sent an empty link.
    $this->withoutLocalizationMiddleware()->actingAs($user)->from(route('courses.register.continue'))->withSession(['pending_selected_course_ids' => []])
        ->post(route('courses.register.resume-link'))->assertRedirect(route('courses.register.continue'))->assertSessionHas('error');
});
