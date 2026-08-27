<?php

use App\Domains\Courses\Actions\ComposeCourseConversionSignalsAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Website\Models\ContactInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function conversionCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'W1 Urgency Lab',
        'slug' => 'w1-urgency-lab-'.fake()->unique()->numerify('###'),
        'status' => 'open',
        'seats' => 25,
        'fee' => 800,
        'enrollment_deadline' => now()->addDays(20)->toDateString(),
    ], $overrides));
}

function occupySeats(Course $course, int $count, string $status = 'active'): void
{
    for ($i = 0; $i < $count; $i++) {
        $rs = makeRegistrationStudent(['first_name' => 'Wait', 'last_name' => 'Seat'.$i]);
        DB::table('course_enrollments')->insert([
            'student_id' => $rs->id,
            'course_id' => $course->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('hides seat badges when seats are null and when remaining is above the hide threshold', function () {
    $none = conversionCourse(['seats' => null]);
    $plenty = conversionCourse(['seats' => 30]);
    occupySeats($plenty, 5);

    $action = app(ComposeCourseConversionSignalsAction::class);
    expect($action->execute($none->id)['seats_label'])->toBeNull()
        ->and($action->execute($plenty->id)['seats_label'])->toBeNull()
        ->and($action->execute($plenty->id)['seats_remaining'])->toBe(25);
});

it('uses limited, exact, and full labels without inventing seats', function () {
    $limited = conversionCourse(['seats' => 20]);
    occupySeats($limited, 5);
    $exact = conversionCourse(['seats' => 10]);
    occupySeats($exact, 3);
    $full = conversionCourse(['seats' => 2]);
    occupySeats($full, 2);

    $action = app(ComposeCourseConversionSignalsAction::class);
    expect($action->execute($limited->id)['seats_label'])->toBe('Limited seats')
        ->and($action->execute($limited->id)['seats_remaining'])->toBe(15)
        ->and($action->execute($exact->id)['seats_label'])->toBe('7 seats left')
        ->and($action->execute($full->id)['seats_label'])->toBe('Full — join waiting list')
        ->and($action->execute($full->id)['seats_tone'])->toBe('full');
});

it('shows a deadline badge under 14 days and marks expired courses as hidden from open listing', function () {
    $soon = conversionCourse(['enrollment_deadline' => now()->addDays(5)->toDateString()]);
    $expired = conversionCourse([
        'title' => 'Expired Open Course',
        'slug' => 'expired-open-course',
        'enrollment_deadline' => now()->subDay()->toDateString(),
    ]);

    $action = app(ComposeCourseConversionSignalsAction::class);
    expect($action->execute($soon->id)['deadline_badge'])->toBeTrue()
        ->and($action->execute($soon->id)['deadline_days'])->toBe(5)
        ->and($action->execute($expired->id)['deadline_expired'])->toBeTrue()
        ->and($action->execute($expired->id)['hide_from_open_listing'])->toBeTrue();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertDontSee('Expired Open Course', false);
});

it('shows early-bird strike-through only when meta is active and cheaper than the fee', function () {
    $live = conversionCourse([
        'meta' => [
            'early_bird_active' => true,
            'early_bird_amount' => 500,
            'early_bird_ends_at' => now()->addDays(3)->toDateString(),
        ],
    ]);
    $stale = conversionCourse([
        'slug' => 'stale-early-bird',
        'meta' => [
            'early_bird_active' => true,
            'early_bird_amount' => 500,
            'early_bird_ends_at' => now()->subDay()->toDateString(),
        ],
    ]);

    $action = app(ComposeCourseConversionSignalsAction::class);
    expect($action->execute($live->id)['early_bird']['amount'])->toBe(500.0)
        ->and($action->execute($stale->id)['early_bird'])->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $live))
        ->assertOk()
        ->assertSee('Early bird until', false)
        ->assertSee('500.00', false)
        ->assertSee('800.00', false);
});

it('accepts a waiting-list interest form only when the course is full', function () {
    $full = conversionCourse(['seats' => 1, 'slug' => 'full-waitlist-course']);
    occupySeats($full, 1);
    $open = conversionCourse(['seats' => 10, 'slug' => 'open-waitlist-course']);

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $full))
        ->post(route('public.courses.waitlist', $full), [
            'name' => 'Mariyam Wait',
            'phone' => '7900000',
            'email' => 'wait@example.com',
        ])
        ->assertRedirect();

    expect(ContactInquiry::query()->count())->toBe(1)
        ->and(ContactInquiry::query()->sole()->meta['course_id'])->toBe($full->id)
        ->and(ContactInquiry::query()->sole()->meta['source'])->toBe('waiting_list');

    $this->withoutLocalizationMiddleware()
        ->from(route('public.courses.show', $open))
        ->post(route('public.courses.waitlist', $open), [
            'name' => 'Too Early',
            'phone' => '7900001',
        ])
        ->assertSessionHasErrors('course');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $full))
        ->assertOk()
        ->assertSee('Full — join waiting list', false)
        ->assertDontSee('⚠ Only', false);
});

it('does not import other domain models from new conversion files', function () {
    $files = [
        app_path('Domains/Courses/Actions/ComposeCourseConversionSignalsAction.php'),
        app_path('Domains/Website/Actions/JoinCourseWaitlistAction.php'),
        app_path('Domains/Settings/Actions/GetSettingAction.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\');
    }
    $website = file_get_contents(app_path('Domains/Website/Actions/JoinCourseWaitlistAction.php'));
    expect($website)->not->toMatch('/App\\\\Domains\\\\Courses\\\\Models\\\\/');
});
