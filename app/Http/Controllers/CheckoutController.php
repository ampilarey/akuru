<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Services\BmlConnectService;
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
    public function start(Request $request, Course $course)
    {
        $request->validate([
            'accept_terms' => ['required', Rule::in(['1', 'on', 'yes'])],
            'student_id' => ['nullable', 'exists:registration_students,id'],
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
        $studentId = $request->input('student_id');
        $enrollmentId = $request->input('enrollment_id');

        $payment = DB::transaction(function () use ($course, $user, $studentId, $enrollmentId, $fee) {
            $enrollment = null;
            if ($enrollmentId) {
                $enrollment = CourseEnrollment::where('id', $enrollmentId)
                    ->where('course_id', $course->id)
                    ->firstOrFail();
            }
            if (! $enrollment && $studentId) {
                $enrollment = CourseEnrollment::firstOrCreate(
                    [
                        'student_id' => $studentId,
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
                $student = RegistrationStudent::where('user_id', $user?->id)->first();
                if ($student) {
                    $enrollment = CourseEnrollment::firstOrCreate(
                        [
                            'student_id' => $student->id,
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
                'student_id' => $enrollment?->student_id,
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
