<?php

namespace App\Domains\Admissions\Http\Controllers;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Finance\Services\BmlConnectService;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        protected BmlConnectService $bml
    ) {}

    /**
     * Show checkout page: fee, currency, merchant outlet country, policies, REQUIRED acceptance checkbox.
     */
    public function show(Course $course)
    {
        $fee = (float) ($course->registration_fee_amount ?? 0);
        $currency = $course->registration_fee_currency ?? config('bml.default_currency', 'MVR');

        return view('checkout.course', [
            'course' => $course,
            'fee' => $fee,
            'currency' => $currency,
            'merchant_outlet_country' => 'Maldives',
        ]);
    }

    /**
     * Start payment: validate terms acceptance, create registration + payment, redirect to BML.
     */
    /**
     * The students this person may check out for: themselves and their own
     * children (`students.id` since Deploy 3 slice 2). The same rule
     * registration uses, so this introduces no new policy.
     *
     * @return list<int>
     */
    private function studentIdsFor(?\App\Domains\Identity\Models\User $user): array
    {
        return $user === null ? [] : app(RegisterCourseStudentAction::class)->idsForActor($user->id);
    }

    public function start(Request $request, Course $course)
    {
        $request->validate([
            'accept_terms' => ['required', Rule::in(['1', 'on', 'yes'])],
            'student_id' => ['nullable', 'exists:students,id'],
            'enrollment_id' => ['nullable', 'exists:course_enrollments,id'],
        ], [
            'accept_terms.required' => 'You must accept the Terms & Conditions, Refund Policy, and Privacy Policy to proceed.',
        ]);

        $fee = (float) ($course->registration_fee_amount ?? 0);
        if ($fee < 0.01) {
            return redirect()->route('courses.register.show', $course)
                ->with('error', 'This course has no registration fee. Please complete registration without payment.');
        }

        $user = $request->user();

        // **Scoped to the payer, not merely to the course.**
        //
        // `enrollment_id` was checked against `$course->id` and nothing else,
        // and the transaction below does
        // `$enrollment->update(['payment_status' => 'pending', 'payment_id' => ...])`.
        // So a signed-in visitor could pass a stranger's enrolment id on the
        // same course and repoint that enrolment's payment at their own —
        // leaving somebody else mid-checkout attached to a payment they do not
        // control, and pending if it was abandoned. Enrolment ids are
        // sequential integers.
        //
        // `student_id` was `exists:...,id`, which says the row exists and
        // nothing about whose it is.
        //
        // The set of students a person may act for is the app's own rule, not
        // a new one: themselves and their children.
        $mine = $this->studentIdsFor($user);

        $studentId = $request->input('student_id');
        if ($studentId !== null && ! in_array((int) $studentId, $mine, true)) {
            abort(403);
        }

        $enrollmentId = $request->input('enrollment_id');

        $payment = DB::transaction(function () use ($course, $user, $studentId, $enrollmentId, $fee, $mine) {
            $enrollment = null;
            if ($enrollmentId) {
                $enrollment = CourseEnrollment::where('id', $enrollmentId)
                    ->where('course_id', $course->id)
                    ->whereIn('unified_student_id', $mine)
                    ->firstOrFail();
            }
            if (! $enrollment && $studentId) {
                $enrollment = CourseEnrollment::firstOrCreate(
                    [
                        'unified_student_id' => (int) $studentId,
                        'course_id' => $course->id,
                    ],
                    [
                        'status' => 'pending',
                        'payment_status' => 'required',
                        'created_by_user_id' => $user?->id,
                    ]
                );
            }
            if (! $enrollment) {
                $ownId = $user?->student?->id;
                if ($ownId) {
                    $enrollment = CourseEnrollment::firstOrCreate(
                        [
                            'unified_student_id' => $ownId,
                            'course_id' => $course->id,
                        ],
                        [
                            'status' => 'pending',
                            'payment_status' => 'required',
                            'created_by_user_id' => $user?->id,
                        ]
                    );
                }
            }

            $payment = Payment::create([
                'user_id' => $user?->id,
                'unified_student_id' => $enrollment?->unified_student_id,
                'course_id' => $course->id,
                'amount' => $fee,
                'amount_mvr' => $fee,
                'amount_laar' => $this->bml->mvrToLaari($fee),
                'currency' => $course->registration_fee_currency ?? config('bml.default_currency', 'MVR'),
                'status' => 'initiated',
                'provider' => 'bml',
            ]);

            if ($enrollment) {
                $enrollment->update(['payment_status' => 'pending', 'payment_id' => $payment->id]);
                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'amount' => $fee,
                ]);
            }

            return $payment;
        });

        try {
            $returnUrl = route('payments.return', ['payment' => $payment->id], true);
            $paymentUrl = $this->bml->createTransaction($payment, ['redirect_url' => $returnUrl]);

            return redirect()->away($paymentUrl);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('checkout.course.show', $course)
                ->with('error', $e->getMessage() ?: 'Payment could not be started. Please try again.');
        }
    }
}
