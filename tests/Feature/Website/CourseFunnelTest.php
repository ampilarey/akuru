<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Website\Actions\ComposeCourseFunnelReportAction;
use App\Domains\Website\Actions\RecordFunnelEventAction;
use App\Domains\Website\Enums\FunnelEventName;
use App\Domains\Website\Models\FunnelEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function funnelCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'W16 Funnel Course',
        'slug' => 'w16-funnel-'.fake()->unique()->numerify('###'),
        'status' => 'open',
        'fee' => 180,
        'enrollment_deadline' => now()->addDays(20)->toDateString(),
    ], $overrides));
}

function funnelSyllabusFile(): MediaFile
{
    Storage::fake('public');
    $path = 'syllabi/w16-funnel.pdf';
    Storage::disk('public')->put($path, '%PDF-w16');

    return MediaFile::query()->create([
        'disk' => 'public',
        'path' => $path,
        'mime' => 'application/pdf',
        'original_name' => 'w16-funnel.pdf',
        'size' => 8,
        'visibility' => 'public',
        'process_status' => 'processed',
        'processed_at' => now(),
        'meta' => ['alt' => 'syllabus'],
    ]);
}

it('records course_view when a public course page is shown', function () {
    $course = funnelCourse();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertSee('data-akuru-funnel="register_click"', false)
        ->assertSee('window.akuruFunnel', false);

    $event = FunnelEvent::query()->sole();
    expect($event->course_id)->toBe($course->id)
        ->and($event->name)->toBe(FunnelEventName::CourseView)
        ->and($event->source)->toBe('server');
});

it('stores client register_click and whatsapp_click beacons and rejects payment_completed', function () {
    $course = funnelCourse();

    $this->withoutLocalizationMiddleware()
        ->post(route('public.funnel.store'), [
            'name' => 'register_click',
            'course_id' => $course->id,
        ])
        ->assertNoContent();

    $this->withoutLocalizationMiddleware()
        ->post(route('public.funnel.store'), [
            'name' => 'whatsapp_click',
            'course_id' => $course->id,
        ])
        ->assertNoContent();

    $this->withoutLocalizationMiddleware()
        ->post(route('public.funnel.store'), [
            'name' => 'payment_completed',
            'course_id' => $course->id,
        ])
        ->assertSessionHasErrors('name');

    $this->withoutLocalizationMiddleware()
        ->post(route('public.funnel.store'), [
            'name' => 'register_click',
            'course_id' => $course->id,
            'website' => 'http://spam.test',
        ])
        ->assertNoContent();

    $names = FunnelEvent::query()->orderBy('id')->pluck('name')->map->value->all();
    expect($names)->toBe(['register_click', 'whatsapp_click'])
        ->and(FunnelEvent::query()->where('source', 'client')->count())->toBe(2);
});

it('records registration_started on an open checkout and skips a closed course', function () {
    $open = funnelCourse(['slug' => 'w16-open-checkout']);
    $closed = funnelCourse(['slug' => 'w16-closed-checkout', 'status' => 'closed']);

    $this->withoutLocalizationMiddleware()
        ->get(route('courses.checkout.show', $open))
        ->assertOk();

    expect(FunnelEvent::query()->where('course_id', $open->id)->where('name', 'registration_started')->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->get(route('courses.checkout.show', $closed))
        ->assertOk();

    expect(FunnelEvent::query()->where('course_id', $closed->id)->count())->toBe(0);
});

it('records syllabus_download when a syllabus lead is captured', function () {
    $file = funnelSyllabusFile();
    $course = funnelCourse(['syllabus_media_file_id' => $file->id]);

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $course))
        ->post(route('public.courses.syllabus', $course), [
            'name' => 'Hassan Funnel',
            'mobile' => '7900123',
        ])
        ->assertRedirect();

    expect(FunnelEvent::query()->where('name', 'syllabus_download')->where('course_id', $course->id)->count())->toBe(1);
});

it('records payment_completed through the action and hooks PaymentService webhook success', function () {
    $course = funnelCourse();

    $event = app(RecordFunnelEventAction::class)->execute($course->id, 'payment_completed', 'webhook', [
        'payment_id' => 99,
    ]);

    expect($event)->not->toBeNull()
        ->and($event->name)->toBe(FunnelEventName::PaymentCompleted)
        ->and($event->source)->toBe('webhook')
        ->and($event->meta['payment_id'])->toBe(99);

    $src = file_get_contents(app_path('Domains/Finance/Services/Payment/PaymentService.php'));
    expect($src)->toContain('RecordFunnelEventAction')
        ->and($src)->toContain('payment_completed')
        ->and($src)->toContain('recordPaymentCompletedFunnel')
        ->and($src)->not->toContain('App\\Domains\\Website\\Enums\\');
});

it('lists the admin funnel report and exports CSV', function () {
    $admin = actingPeopleAdmin();
    $course = funnelCourse(['title' => 'W16 Listed Funnel']);
    app(RecordFunnelEventAction::class)->execute($course->id, 'course_view');
    app(RecordFunnelEventAction::class)->execute($course->id, 'register_click', 'client');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.funnel.index'))
        ->assertOk()
        ->assertSee('W16 Listed Funnel', false)
        ->assertSee('Keep iterating W1 content from this funnel', false)
        ->assertSee('ADR-022', false);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.funnel.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('W16 Listed Funnel')
        ->and($csv)->toContain('course_view')
        ->and($csv)->toContain('Keep iterating W1 content from this funnel');
});

it('applies the recorded iterate-from-data decision rule', function () {
    $action = app(ComposeCourseFunnelReportAction::class);

    expect($action->decide([]))->toBe('Not enough data yet — keep collecting.')
        ->and($action->decide([
            'course_view' => 20,
            'register_click' => 0,
        ]))->toBe('Iterate W1 content (hero, urgency, outcomes, sticky CTA) — few enroll clicks per view.')
        ->and($action->decide([
            'course_view' => 30,
            'register_click' => 10,
            'registration_started' => 2,
        ]))->toBe('Iterate checkout first step — clicks are not becoming registrations.')
        ->and($action->decide([
            'course_view' => 40,
            'register_click' => 20,
            'registration_started' => 10,
            'payment_completed' => 1,
        ]))->toBe('Iterate payment / fee copy — registrations are not completing payment.')
        ->and($action->decide([
            'course_view' => 8,
            'register_click' => 2,
            'whatsapp_click' => 6,
        ]))->toBe('WhatsApp is the stronger path — keep the sticky WhatsApp CTA and iterate enroll later.')
        ->and($action->decide([
            'course_view' => 10,
            'register_click' => 4,
            'registration_started' => 3,
            'payment_completed' => 2,
        ]))->toBe('Keep iterating W1 content from this funnel — no stage is clearly stuck.');
});

it('does not import other-domain models or Website enums across domains from new W1.6 files', function () {
    $website = [
        app_path('Domains/Website/Actions/RecordFunnelEventAction.php'),
        app_path('Domains/Website/Actions/ComposeCourseFunnelReportAction.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/FunnelEventController.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/FunnelController.php'),
        app_path('Domains/Website/Models/FunnelEvent.php'),
    ];
    foreach ($website as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toMatch('/App\\\\Domains\\\\Courses\\\\Models\\\\/')
            ->and($src)->not->toContain('App\\Domains\\Hifz\\');
    }

    $admissions = file_get_contents(app_path('Domains/Admissions/Http/Controllers/CourseRegistrationController.php'));
    $finance = file_get_contents(app_path('Domains/Finance/Services/Payment/PaymentService.php'));
    expect($admissions)->toContain('RecordFunnelEventAction')
        ->and($admissions)->toContain('registration_started')
        ->and($admissions)->not->toContain('App\\Domains\\Website\\Enums\\')
        ->and($finance)->not->toContain('App\\Domains\\Website\\Enums\\');
});
