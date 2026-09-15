<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A public page shows the message its controller redirected with.
 *
 * ## The defect
 *
 * `resources/views/public/layouts/public.blade.php` rendered **no flash
 * messages at all**, so every `redirect()->route('public…')->with('error', …)`
 * from a public controller showed the visitor nothing. The message was set,
 * carried, and dropped on the floor.
 *
 * ## How it was found, and why it matters
 *
 * By walking the **paid** registration funnel. A family finishes registering,
 * the enrolment is `pending` with a `payments` row `initiated`, and the page
 * offers one button: **Proceed to payment**. With the gateway not configured —
 * which is the state of production today, per `OWNER_ACTIONS` item 2 —
 * `CourseRegistrationController::paymentRetry` redirects to the course list
 * with *"Payment gateway not configured"*.
 *
 * The family saw a course list. No message, money outstanding, nothing to act
 * on. Same shape as the dead-end resume link (STATUS §5dr) on the other payment
 * path: the code was right and the person was told nothing.
 *
 * ## Why the test asserts `role="alert"`
 *
 * The walk that found this first reported success against a looser selector
 * that matched a nav element and returned *"Log out"* as the explanation — a
 * check that passed on silence. The alert role is what a screen reader
 * announces and what this fix actually adds, so it is what gets asserted.
 */
it('shows an error a public controller redirected with', function () {
    $this->withoutLocalizationMiddleware()
        ->withSession(['error' => 'Payment gateway not configured'])
        ->get(route('public.courses.index'))
        ->assertOk()
        ->assertSee('Payment gateway not configured', false)
        ->assertSee('role="alert"', false);
});

it('shows a success message too, so the layout is not error-only', function () {
    $this->withoutLocalizationMiddleware()
        ->withSession(['success' => 'Your seat is reserved'])
        ->get(route('public.courses.index'))
        ->assertOk()
        ->assertSee('Your seat is reserved', false);
});

it('says nothing when there is nothing to say', function () {
    // A layout that always renders an empty alert box is its own defect, and
    // would make the assertions above pass without carrying anything.
    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.index'))
        ->assertOk()
        ->assertDontSee('role="alert"', false);
});
