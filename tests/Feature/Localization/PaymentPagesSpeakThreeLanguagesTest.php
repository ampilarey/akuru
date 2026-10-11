<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Finance\Actions\ListManualPaymentMethodsAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The payment pages in Dhivehi and Arabic (BACKLOG C21, slice PY1, STATUS
 * §5re).
 *
 * The page back from the bank, a payment's receipt, the page for a return
 * that lost its reference, the course checkout and the bank's required lines
 * (on the registration's code step too) were English throughout, and so was
 * what the server said on them. The bank's return lands outside the
 * localised routes, so even a translated page would have come back in
 * English to a family who paid from a Dhivehi one.
 *
 * Reading them found what the pages said wrong:
 * - the page back from the bank said "Your registration has been confirmed"
 *   for every payment, a family's fee and a course the office still approves
 *   among them, and sent everyone on to the registration's last page, which
 *   sent a fee or a learner-catalogue purchase on to the course list with an
 *   error;
 * - it offered a password to nobody: it asked whether the account had none,
 *   and every account has one;
 * - a payment the bank turned down waited a minute to say "If your payment
 *   was completed…";
 * - the course checkout said a refusal twice, printed its form's refusals as
 *   escaped `<li>` markup, and showed the exception's own text when the
 *   payment could not start;
 * - the receipt's title lost its number whenever `local_id` was empty, and
 *   its date and its "(MVT)" were English.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['identity.verification.enforce' => false]);
    $this->withoutLocalizationMiddleware();
});

function py1Views(): array
{
    return [
        'payments/processing.blade.php',
        'payments/receipt.blade.php',
        'payments/return-missing.blade.php',
        'checkout/course.blade.php',
        'components/payment-trust-bar.blade.php',
    ];
}

function py1ServerFiles(): array
{
    return [
        'app/Domains/Finance/Http/Controllers/PaymentController.php',
        'app/Domains/Finance/Http/Controllers/PaymentReceiptController.php',
        'app/Domains/Admissions/Http/Controllers/CheckoutController.php',
        'app/Domains/Finance/Actions/BuildPaymentReceiptAction.php',
        'app/Domains/Finance/Enums/PaymentMethod.php',
    ];
}

function py1Book(string $locale, string $book = 'payments'): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

function py1Payment(array $attributes = []): Payment
{
    return Payment::query()->create($attributes + [
        'user_id' => User::factory()->create(['force_password_change' => false])->id,
        'amount' => 250,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-PY1-'.uniqueFixtureSuffix(),
        'paid_at' => now(),
        'confirmed_at' => now(),
    ]);
}

/** The bank has nothing to say yet about a payment still pending. */
function py1QuietBank(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: false, merchantReference: '', providerReference: null, status: 'pending', rawPayload: [], isConfirmed: false);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

it('prints no English of its own on the payment pages and the bank\'s lines', function () {
    $found = [];
    foreach (py1Views() as $view) {
        $path = resource_path('views/'.$view);
        // The institute's web address is an address, the same in every language.
        foreach ([...bladeBareEnglish($path, ['akuru.edu.mv']), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
        // `__('English sentence')` with no such phrase in any book is English in every language.
        preg_match_all("/__\\('([A-Z][^']*\\s[^']*)'\\)/", (string) file_get_contents($path), $sentences);
        foreach ($sentences[1] as $sentence) {
            if (! in_array($sentence, ['Akuru Institute', 'Terms of Service', 'Refund Policy', 'Privacy Policy'], true)) {
                $found[] = "{$view}: __('{$sentence}')";
            }
        }
    }

    expect($found)->toBe([]);
});

it('keys every phrase the pages and the server files name in three languages, placeholders kept', function () {
    $keys = [];
    foreach ([...array_map(fn (string $view) => resource_path('views/'.$view), py1Views()), ...array_map('base_path', py1ServerFiles())] as $source) {
        // Through `__()` only: `payments.processing` is a view's name and `payments.return` a route's.
        preg_match_all("/__\\('(payments|register|nav)\\.([a-z_0-9]+)'/", (string) file_get_contents($source), $found, PREG_SET_ORDER);
        foreach ($found as [, $book, $key]) {
            $keys["{$book}.{$key}"] = [$book, $key];
        }
    }
    expect(array_keys($keys))->toContain('payments.confirmed', 'payments.receipt', 'payments.missing_check', 'payments.agree', 'payments.secure_ssl', 'payments.method_cash', 'payments.error_no_receipt', 'register.error_payment_start', 'nav.my_enrolments');

    foreach ($keys as [$book, $key]) {
        $english = py1Book('en', $book)[$key] ?? null;
        expect($english)->not->toBeNull("{$book}.{$key} has no English");
        preg_match_all('/:[a-z_]+/', (string) $english, $placeholders);
        foreach (['dv' => '/\p{Thaana}/u', 'ar' => '/\p{Arabic}/u'] as $locale => $script) {
            $line = py1Book($locale, $book)[$key] ?? '';
            expect($line)->toMatch($script, "{$book}.{$key} in {$locale}");
            foreach ($placeholders[0] as $placeholder) {
                expect($line)->toContain($placeholder);
            }
        }
    }
    // The policy names and the address are the public book's, as the footer says them.
    foreach (['Terms of Service', 'Refund Policy', 'Privacy Policy', 'Akuru Institute', 'address', 'Phone', 'Email'] as $key) {
        expect(py1Book('dv', 'public')[$key] ?? '')->toMatch('/\p{Thaana}/u')
            ->and(py1Book('ar', 'public')[$key] ?? '')->toMatch('/\p{Arabic}/u');
    }
});

it('leaves none of the payment English on the server', function () {
    foreach (py1ServerFiles() as $file) {
        expect(refusalEnglishIn($file))->toBe([], $file);
    }
});

it('names the checkout\'s field, and sets codes and numbers left to right', function () {
    foreach (py1Views() as $view) {
        expect(unnamedFields((string) file_get_contents(resource_path('views/'.$view))))->toBe([], $view);
    }
    $receipt = (string) file_get_contents(resource_path('views/payments/receipt.blade.php'));
    expect(substr_count($receipt, 'dir="ltr"'))->toBeGreaterThanOrEqual(6)
        ->and($receipt)->toContain('<span class="rtl-flip" aria-hidden="true">←</span>');
});

it('comes back from the bank in the language the family paid in, the payment confirmed and the bank\'s lines in it', function () {
    $dv = py1Book('dv');
    $payment = py1Payment();

    $page = $this->withSession(['locale' => 'dv'])->get(route('payments.bml.return', ['ref' => $payment->merchant_reference]))->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee($dv['processing_title'])->assertSee($dv['confirmed'])->assertSee($dv['confirmed_hint'])->assertSee($dv['keep_receipt'])
        ->assertSee($dv['continue'])->assertSee($dv['checking'])->assertSee($dv['slow'])->assertSee($dv['back_home'])
        // The bank's required lines, on the trust bar.
        ->assertSee($dv['card_brands'])->assertSee($dv['maldives'])->assertSee($dv['mvr'])->assertSee($dv['secure_ssl'])->assertSee($dv['retain'])
        ->assertSee('id="payment-success" class=""', false)->assertSee('id="payment-pending" class="hidden"', false)
        ->assertDontSee('Payment successful')->assertDontSee('Merchant outlet country')->getContent();
    // What was English is gone, and the registration was not said to be confirmed.
    expect($page)->not->toContain('Your registration has been confirmed');

    // Arabic, from a browser that asks for it, with no language remembered.
    $ar = py1Book('ar');
    $this->flushSession();
    $this->get(route('payments.bml.return', ['ref' => $payment->merchant_reference]), ['Accept-Language' => 'ar'])->assertOk()
        ->assertSee('dir="rtl"', false)->assertSee($ar['confirmed'])->assertSee($ar['secure_ssl']);
});

it('sends each payment on to what it was for', function () {
    // A family's fee, paid from the portal.
    $fee = py1Payment(['payable_type' => 'invoice', 'payable_id' => 1]);
    $this->get(route('payments.bml.return', ['ref' => $fee->merchant_reference]))->assertOk()
        ->assertSee('href="'.route('portal.invoices').'"', false)->assertDontSee('href="'.route('courses.register.complete').'"', false);

    // A registration's own payment, whose session the last page reads.
    $registration = py1Payment();
    $this->withSession(['pending_payment_ref' => $registration->merchant_reference])
        ->get(route('payments.bml.return', ['ref' => $registration->merchant_reference]))->assertOk()
        ->assertSee('href="'.route('courses.register.complete').'"', false);

    // Any other course payment: the learner's enrolments, with its receipt.
    $course = py1Payment(['payable_type' => 'course_enrollment', 'payable_id' => 1]);
    $this->flushSession();
    $this->get(route('payments.bml.return', ['ref' => $course->merchant_reference]))->assertOk()
        ->assertSee('href="'.route('my.enrollments').'"', false)->assertDontSee('href="'.route('courses.register.complete').'"', false);
});

it('offers a password to an account made by a code, and to no other', function () {
    $dv = py1Book('dv');
    $codeMade = User::factory()->create(['force_password_change' => true]);
    $payment = py1Payment(['user_id' => $codeMade->id]);

    // `payments.return` is a localised route: `/dv/payments/return/…` sets the language.
    app()->setLocale('dv');
    $this->actingAs($codeMade)->get(route('payments.return', $payment))->assertOk()
        ->assertSee('data-testid="payment-set-password"', false)->assertSee($dv['set_password_title'])->assertSee($dv['set_password_hint'])
        ->assertSee($dv['set_password'])->assertSee($dv['skip'])->assertSee('href="'.route('account.set-password').'"', false);

    $withPassword = User::query()->find(py1Payment()->user_id);
    $this->actingAs($withPassword)->get(route('payments.return', Payment::query()->where('user_id', $withPassword->id)->first()))->assertOk()
        ->assertDontSee('data-testid="payment-set-password"', false)->assertSee('data-testid="payment-continue"', false);
});

it('says at once that the bank turned a payment down, and still checks one it has not answered', function () {
    $dv = py1Book('dv');
    $declined = py1Payment(['status' => 'failed', 'paid_at' => null, 'confirmed_at' => null]);

    $this->withSession(['locale' => 'dv'])->get(route('payments.bml.return', ['ref' => $declined->merchant_reference]))->assertOk()
        ->assertSee($dv['not_paid'])->assertSee($dv['not_paid_hint'])
        ->assertSee('id="payment-failed" class=""', false)->assertSee('id="payment-pending" class="hidden"', false)
        ->assertSee('id="payment-success" class="hidden"', false);

    py1QuietBank();
    $pending = py1Payment(['status' => 'pending', 'paid_at' => null, 'confirmed_at' => null]);
    $this->withSession(['locale' => 'dv'])->get(route('payments.bml.return', ['ref' => $pending->merchant_reference]))->assertOk()
        ->assertSee('id="payment-pending" class=""', false)->assertSee('id="payment-failed" class="hidden"', false)
        ->assertSee($dv['checking'])->assertSee($dv['checking_hint']);
});

it('serves the page for a lost reference in Dhivehi, its link where the language puts it', function () {
    $dv = py1Book('dv');
    [$before, $after] = explode(':enrollments', $dv['missing_check']);

    $this->withSession(['locale' => 'dv'])->get(route('payments.bml.return'))->assertOk()
        ->assertSee('dir="rtl"', false)->assertSee($dv['missing_title'])->assertSee($dv['missing_intro'])->assertSee($dv['missing_paid'])
        ->assertSee($dv['missing_paid_hint'])->assertSee(__('register.back_to_courses', [], 'dv'))
        ->assertSee(e($before).'<a href="'.route('my.enrollments').'" class="text-blue-600 underline">'.e($dv['your_enrollments']).'</a>'.e($after), false);

    $this->withSession(['locale' => 'dv'])->get(route('payments.bml.return', ['ref' => 'AKURU-PY1-NOWHERE']))->assertOk()
        ->assertSee($dv['reference'])->assertSee('<code dir="ltr">AKURU-PY1-NOWHERE</code>', false);
});

it('serves a receipt in Dhivehi: its words, the month, the time, the method and where it was paid', function () {
    $dv = py1Book('dv');
    app()->setLocale('dv');
    $payment = py1Payment([
        'provider' => 'manual',
        'payment_method' => 'cash',
        'local_id' => null,
        'paid_at' => '2026-10-11 09:30:00',
        'merchant_reference' => 'AKURU-PY1-CASH',
    ]);
    $payer = User::query()->find($payment->user_id);

    $page = $this->actingAs($payer)->get("/payments/{$payment->id}/receipt")->assertOk()
        ->assertSee($dv['receipt'])->assertSee($dv['receipt_confirmed'])->assertSee($dv['billed_to'])->assertSee($dv['date'])
        ->assertSee($dv['col_description'])->assertSee($dv['col_student'])->assertSee($dv['col_amount'])->assertSee($dv['total'])
        ->assertSee($dv['paid_through'])->assertSee($dv['gateway_manual'])->assertSee($dv['payment_method'])->assertSee($dv['method_cash'])
        ->assertSee($dv['reference'])->assertSee($dv['keep_copy'])->assertSee($dv['keep_copy_hint'])->assertSee($dv['print'])
        ->assertSee($dv['line_course'])->assertSee(__('nav.my_enrolments', [], 'dv'))->assertSee(__('public.Akuru Institute', [], 'dv'))
        // The month by its Dhivehi name, the time as the Maldives'.
        ->assertSee('11 އޮކްޓޫބަރު 2026')->assertSee(__('payments.maldives_time', ['time' => '09:30'], 'dv'))
        ->assertDontSee('(MVT)')->assertDontSee('Recorded at the institute')->assertDontSee('Billed to')->getContent();

    // The title kept its number: `.` bound tighter than `??`, and an empty local_id lost it.
    expect($page)->toContain('<title>'.e(__('payments.receipt_title', ['number' => '#AKURU-PY1-CASH'], 'dv')));
});

it('names the methods the office records by hand in the page\'s language', function () {
    app()->setLocale('dv');
    $labels = array_column(app(ListManualPaymentMethodsAction::class)->execute(), 'label');
    expect($labels)->toHaveCount(5);
    foreach ($labels as $label) {
        expect($label)->toMatch('/\p{Thaana}/u');
    }
});

it('serves the course checkout in Dhivehi, says a refusal once and as a list, and keeps the failure\'s own text off the page', function () {
    $dv = py1Book('dv');
    app()->setLocale('dv');
    $user = User::factory()->create();
    $course = Course::factory()->create(['title' => 'PY1 Seerah', 'registration_fee_amount' => 150, 'registration_fee_currency' => 'MVR']);

    [$before] = explode(':terms', $dv['agree']);
    $this->actingAs($user)->get(route('checkout.course.show', $course))->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('payments.checkout_title', ['course' => 'PY1 Seerah'], 'dv'))->assertSee($dv['course_registration'])
        ->assertSee(__('payments.fee', ['amount' => '150.00 MVR'], 'dv'))->assertSee(__('payments.outlet_country', ['country' => $dv['maldives']], 'dv'))
        ->assertSee($dv['we_accept'])->assertSee($dv['other_cards'])->assertSee($dv['read_first'])->assertSee($dv['no_duties'])
        ->assertSee(trim($before))->assertSee(__('public.Terms of Service', [], 'dv'))->assertSee(__('public.Refund Policy', [], 'dv'))
        ->assertSee($dv['secure'])->assertSee(__('payments.pay', ['amount' => '150.00 MVR'], 'dv'))->assertSee(__('public.address', [], 'dv'))
        ->assertSee('<label for="accept_terms"', false)->assertDontSee('Merchant outlet country');

    // The terms unticked: the refusal as a list item, not as escaped markup.
    $this->actingAs($user)->from(route('checkout.course.show', $course))->post(route('payments.course.start', $course), [])
        ->assertRedirect(route('checkout.course.show', $course));
    $this->actingAs($user)->get(route('checkout.course.show', $course))->assertOk()
        ->assertSee('<li>'.e(__('register.error_terms_all', [], 'dv')).'</li>', false)->assertDontSee('&lt;li&gt;', false);

    // No gateway here, so the start fails: the page says so in its language, once, and never the exception's text.
    $this->actingAs($user)->post(route('payments.course.start', $course), ['accept_terms' => '1'])
        ->assertRedirect(route('checkout.course.show', $course))->assertSessionHas('error', __('register.error_payment_start', [], 'dv'));
    $page = $this->actingAs($user)->get(route('checkout.course.show', $course))->assertOk()
        ->assertDontSee('Payment gateway not configured')->getContent();
    expect(substr_count($page, e(__('register.error_payment_start', [], 'dv'))))->toBe(1);
});

it('says the old payment start\'s refusals in the page\'s language', function () {
    app()->setLocale('dv');

    $this->post(route('payments.bml.initiate'))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', py1Book('dv')['error_reference']);
    $this->post(route('payments.bml.initiate'), ['merchant_reference' => 'AKURU-PY1-NOWHERE'])->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', __('register.error_payment_missing', [], 'dv'));
});
