<?php

use App\Domains\Admissions\Models\RegistrationFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A family whose payment reference goes missing is told what actually happens.
 *
 * ## The defect
 *
 * `payments/bml/return` without a reference renders a page that used to say:
 * *"If you completed a payment, please **resume your registration** or contact
 * us for assistance"*, linking to `courses.register.resume`.
 *
 * **That link could never work.** `registration_flows` has two readers —
 * `findResumable()` and `latestActiveForUser()` — and **no writer anywhere in
 * the application**: nothing constructs a `RegistrationFlow`, so the resume
 * route always falls through to *"No active registration found. Please start
 * again from a course page."*
 *
 * A family who may have just paid was being sent to a dead end and told to
 * start over. On the money path.
 *
 * ## What replaces it
 *
 * What actually happens. The bank's webhook is the authority on payment
 * (rule 12), and confirming a payment activates the enrolment inside that same
 * transaction — `PaymentConfirmed` is the event domains listen to. So a lost
 * return reference costs the family nothing and asks nothing of them, and the
 * page now says so and points at their enrolments.
 *
 * ## Why the second test is a grep
 *
 * The page's copy is only true while `registration_flows` stays unwritten. The
 * day somebody builds the resume feature, that assertion should fail and send
 * them back here — the two have to move together, and a comment would not
 * have made that happen.
 */
it('tells a family their payment will still be confirmed, and offers no dead end', function () {
    $response = $this->withoutLocalizationMiddleware()
        ->get(route('payments.bml.return'));

    $response->assertOk()
        ->assertSee('If you completed the payment, it will still be confirmed.', false)
        ->assertSee(route('my.enrollments'), false);

    // The link that could not work.
    $response->assertDontSee(route('courses.register.resume'), false);
    $response->assertDontSee('resume your registration', false);
});

it('still shows the reference when the bank sent one it does not know', function () {
    $this->withoutLocalizationMiddleware()
        ->get(route('payments.bml.return', ['ref' => 'AKURU-NOT-A-PAYMENT']))
        ->assertOk()
        ->assertSee('AKURU-NOT-A-PAYMENT', false);
});

it('has no writer for the table the resume route reads', function () {
    $sources = '';

    foreach (['app', 'routes', 'database'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources .= "\n".file_get_contents($file->getPathname());
            }
        }
    }

    $writes = preg_match('/RegistrationFlow::(create|firstOrCreate|updateOrCreate|make)\b/', $sources)
        || preg_match('/new RegistrationFlow\b/', $sources);

    expect($writes)->toBeFalsy(
        'Something now creates a RegistrationFlow, so the resume route may work — which is good, and '
        ."means `payments/return-missing` should offer it again.\n\n"
        .'That page currently tells families the payment will be confirmed without them and gives them no '
        .'resume link, because there was nothing to resume. Update the page and this test together.'
    );

    // And the readers are still there, so this is a gap rather than a removal.
    expect(method_exists(RegistrationFlow::class, 'findResumable'))->toBeTrue();
});
