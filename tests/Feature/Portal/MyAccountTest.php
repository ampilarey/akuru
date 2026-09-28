<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * *My account* and *My enrolments* inside the shell (docs/SIGN_IN_PLAN.md
 * ID2b). They replace the old course portal's five Blade pages, the public
 * course dashboard a person with no role landed on, and the Blade My
 * enrolments page — all in the website's layout.
 */
function myAccountHolder(): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);

    return User::factory()->create();
}

/**
 * A child the holder registered on the website, awaiting the office, and an
 * enrolment for them — paid through a confirmed payment when `$paid`.
 */
function myAccountChildEnrolment(User $user, string $title, bool $paid = false): CourseEnrollment
{
    $child = app(RegisterCourseStudentAction::class)->forChild((int) $user->id, [
        'first_name' => 'Maryam', 'last_name' => 'Rasheed', 'dob' => '2016-04-01', 'national_id' => 'A'.random_int(100000, 999999),
    ], 'mother');
    $course = Course::factory()->create(['title' => $title]);
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'unified_student_id' => $child['id'],
        'status' => $paid ? 'active' : 'pending',
        'payment_status' => $paid ? 'confirmed' : 'pending',
        'enrolled_at' => $paid ? now() : null,
        'created_by_user_id' => $user->id,
    ]);
    if ($paid) {
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'unified_student_id' => $child['id'],
            'course_id' => $course->id,
            'amount' => 250,
            'currency' => 'MVR',
            'status' => 'confirmed',
            'payable_type' => 'course_enrollment',
            'payable_id' => $enrollment->id,
            'merchant_reference' => 'ACC-'.$enrollment->id,
        ]);
        $enrollment->update(['payment_id' => $payment->id]);
    }

    return $enrollment;
}

it('lands a person with no other workspace on My account, inside the shell, with what is theirs', function () {
    $user = myAccountHolder();
    myAccountChildEnrolment($user, 'Quran for children');

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard'))->assertRedirect(route('account.home'));
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('account.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/AccountHome')
            ->where('auth.workspace', 'account')
            ->where('auth.workspaces.0.href', '/my-account')
            ->where('nav.primary', fn ($bar) => collect($bar)->pluck('label')->all() === ['My account', 'My enrolments', 'Browse courses'])
            ->where('nav.groups', fn ($groups) => collect($groups)->pluck('key')->all() === ['education', 'me'])
            // ID2c: the child the office has not checked yet.
            ->where('children_waiting.0.name', 'Maryam Rasheed')
            ->where('children_waiting.0.refused', false)
            ->where('enrolments.0.course', 'Quran for children')
            ->where('enrolments.0.student', 'Maryam Rasheed')
            ->where('enrolments.0.state', 'waiting_payment')
            ->where('enrolments_total', 1)
            ->where('t.home_title', 'My account'));
});

it('sends anyone who holds another workspace to its home', function () {
    $parent = myAccountHolder();
    $parent->assignRole('parent');
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('account.home'))->assertRedirect(route('dashboard'));

    $vendor = myAccountHolder();
    $vendor->assignRole('vendor');
    $this->withoutLocalizationMiddleware()->actingAs($vendor)->get(route('account.home'))->assertRedirect(route('dashboard'));

    // And a person with no other workspace is no longer shown the family portal.
    $this->withoutLocalizationMiddleware()->actingAs(myAccountHolder())->get(route('portal.home'))->assertRedirect(route('dashboard'));
});

it('lists every enrolment this login made and its course payments, with a receipt only where the money is confirmed', function () {
    $user = myAccountHolder();
    $paid = myAccountChildEnrolment($user, 'Arabic for children', paid: true);
    myAccountChildEnrolment($user, 'Seerah for children');
    // Someone else's enrolment and payment are not theirs to see.
    myAccountChildEnrolment(User::factory()->create(), 'Somebody else', paid: true);
    // A payment for something that is not a course — the Bookstore's, say — has a page of its own.
    Payment::query()->create(['user_id' => $user->id, 'amount' => 90, 'status' => 'confirmed', 'payable_type' => 'bookshop_order', 'payable_id' => 1, 'merchant_reference' => 'SHOP-1']);

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('my.enrollments'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/MyEnrolments')
            ->has('enrolments', 2)
            ->where('enrolments', fn ($rows) => collect($rows)->pluck('course')->sort()->values()->all() === ['Arabic for children', 'Seerah for children'])
            ->where('enrolments', fn ($rows) => collect($rows)->firstWhere('course', 'Arabic for children')['receipt_href'] === route('payment.receipt', $paid->payment_id, false)
                && collect($rows)->firstWhere('course', 'Arabic for children')['payment'] === 'paid'
                && collect($rows)->firstWhere('course', 'Seerah for children')['receipt_href'] === null
                && collect($rows)->firstWhere('course', 'Seerah for children')['state'] === 'waiting_payment')
            ->has('payments', 1)
            ->where('payments.0.for', 'Arabic for children')
            ->where('payments.0.amount', '250.00')
            ->where('payments.0.status', 'confirmed')
            ->where('payments.0.receipt_href', route('payment.receipt', $paid->payment_id, false))
            ->where('export_href', '/my-enrollments/export'));

    // The receipt it links opens for them.
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('payment.receipt', $paid->payment_id))->assertOk();

    $csv = $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('my.enrollments.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('enrolment,"Arabic for children","Maryam Rasheed",active,paid')
        ->toContain('payment,ACC-'.$paid->id)
        ->not->toContain('Somebody else')
        ->not->toContain('SHOP-1');
});

it('says so when the registration flow sends a person here because they are already enrolled', function () {
    $user = myAccountHolder();

    $this->withoutLocalizationMiddleware()->actingAs($user)->withSession(['info' => 'You are already enrolled in "Seerah" — Active.'])
        ->get(route('my.enrollments'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flash.info', 'You are already enrolled in "Seerah" — Active.')->has('enrolments', 0));
});

it('keeps the old course portal addresses as redirects into the shell', function () {
    $user = myAccountHolder();
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($user);

    $as()->get('/portal')->assertRedirect(route('dashboard'));
    $as()->get('/portal/dashboard')->assertRedirect(route('dashboard'));
    $as()->get('/portal/enrollments')->assertRedirect(route('my.enrollments'));
    $as()->get('/portal/payments')->assertRedirect(route('my.enrollments'));
    $as()->get('/portal/certificates')->assertRedirect(route('learn.dashboard'));
    $as()->get('/portal/profile')->assertRedirect(route('profile.edit'));
    // Its profile form went with it; the profile page has its own.
    $as()->post('/portal/profile', ['name' => 'Changed'])->assertStatus(405);
    expect($user->fresh()->name)->not->toBe('Changed');
});

it('shows My enrolments in Family, My learning and My account, and nowhere else', function () {
    $menus = [];
    foreach (['family' => ['parent'], 'learner' => [], 'school' => ['teacher'], 'vendor' => ['vendor'], 'account' => []] as $workspace => $roles) {
        $user = myAccountHolder();
        if ($roles !== []) {
            $user->assignRole($roles);
        }
        $nav = app(\App\Support\Navigation\BuildNavigationAction::class)->execute($user, 'en', $workspace);
        $menus[$workspace] = collect($nav['groups'])->flatMap(fn ($group) => array_column($group['items'], 'href'))->contains('/my-enrollments');
    }

    expect($menus)->toBe(['family' => true, 'learner' => true, 'school' => false, 'vendor' => false, 'account' => true]);
});
