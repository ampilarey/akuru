<?php

namespace App\Domains\Admissions\Http\Controllers;

use App\Domains\Courses\Actions\ActivateEnrollmentAction;
use App\Domains\Courses\Actions\ListAdminEnrollmentsAction;
use App\Domains\Courses\Actions\SuspendEnrollmentAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\ListAdminPaymentsAction;
use App\Domains\Finance\Actions\ListManualPaymentMethodsAction;
use App\Domains\Finance\Actions\RecordManualPaymentAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Http\Controllers\Controller;
use App\Mail\EnrollmentStatusMail;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The office's enrolments and payments (docs/ADMIN_PANEL.md). The two lists
 * are Inertia since C9 slice 4 (STATUS §5jf), with their strings keyed for
 * Dhivehi and Arabic; the one-enrolment page (`show`) and its decisions are
 * still Blade until their own slice. `role:super_admin|admin|headmaster` on
 * the route group.
 */
class AdminEnrollmentController extends Controller
{
    private const FILTERS = ['search', 'course_id', 'status', 'payment_status'];

    public function index(Request $request): Response
    {
        $filters = $request->only(self::FILTERS);

        return Inertia::render('Admissions/Enrollments', app(ListAdminEnrollmentsAction::class)->execute($filters) + [
            'filters' => array_map(fn ($key) => (string) ($filters[$key] ?? ''), array_combine(self::FILTERS, self::FILTERS)),
            'statuses' => ListAdminEnrollmentsAction::STATUSES,
            'payment_statuses' => ListAdminEnrollmentsAction::PAYMENT_STATUSES,
            't' => trans('admin'),
        ]);
    }

    public function show(CourseEnrollment $enrollment)
    {
        $enrollment->load(['student.guardians', 'course', 'payment.items.course', 'creator', 'decider']);
        // SPEC §38's payment-method vocabulary, fetched through Finance's
        // Action rather than its enum (rule 3).
        $paymentMethods = app(ListManualPaymentMethodsAction::class)->execute();

        return view('admin.enrollments.show', compact('enrollment', 'paymentMethods'));
    }

    public function activate(CourseEnrollment $enrollment)
    {
        try {
            app(ActivateEnrollmentAction::class)->execute($enrollment, (int) auth()->id());
        } catch (ValidationException $e) {
            // Same shape as suspend/reinstate below: this screen is Blade and
            // does not render a validation bag, so a thrown seat refusal would
            // look to the admin like the button did nothing.
            return back()->with('error', $e->validator->errors()->first());
        }

        $this->notifyUser($enrollment, 'active');
        $this->sendActivationSms($enrollment);

        return back()->with('success', 'Enrollment activated and student notified via SMS.');
    }

    public function reject(CourseEnrollment $enrollment)
    {
        app(\App\Domains\Courses\Actions\RejectEnrollmentAction::class)->execute($enrollment, (int) auth()->id());

        $this->notifyUser($enrollment, 'rejected');
        $this->sendRejectionSms($enrollment);

        return back()->with('success', 'Enrollment rejected and student notified.');
    }

    /**
     * SPEC §23's sixth status. It had no writer anywhere in the app, which made
     * §23's own seat rule — "Cancelled/suspended enrollments should not count
     * as active seats" — a rule about something that could not happen.
     *
     * Deliberately not an SMS: activate and reject tell the student because a
     * decision was made about their application. A suspension is usually the
     * opening of a conversation the office is already having with them, and a
     * automated message is the wrong way to start it. The rule this slice is
     * enforcing is about the seat, not about notification.
     */
    public function suspend(CourseEnrollment $enrollment)
    {
        try {
            app(SuspendEnrollmentAction::class)->execute($enrollment, null, (int) auth()->id());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Enrollment suspended. The seat is released and their record is kept.');
    }

    public function reinstate(CourseEnrollment $enrollment)
    {
        try {
            app(SuspendEnrollmentAction::class)->reinstate($enrollment, (int) auth()->id());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Enrollment reinstated.');
    }

    /**
     * SPEC §11.7's "Access starts at" / "Access ends at".
     *
     * Neither existed anywhere, so a student's access to a course had no time
     * dimension: `AuthorizeLessonAccessAction` gated on enrolment status and
     * §26's unlock rules and nothing else. An enrolment could not begin later
     * and could not run out.
     *
     * Null at either end means unbounded there, which is what every row
     * created before this holds — so clearing a field is a real operation, not
     * a way of leaving it alone.
     */
    public function setAccessWindow(Request $request, CourseEnrollment $enrollment)
    {
        try {
            app(\App\Domains\Courses\Actions\SetEnrollmentAccessWindowAction::class)->execute(
                $enrollment,
                $request->only(['access_starts_at', 'access_ends_at']),
                (int) auth()->id(),
            );
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Access window saved.');
    }

    public function export(Request $request)
    {
        $enrollments = app(ListAdminEnrollmentsAction::class)->query($request->only(self::FILTERS))->get();

        $filename = 'enrollments-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($enrollments) {
            $handle = fopen('php://output', 'w');

            Csv::put($handle, [
                'ID', 'Course', 'Student Name', 'Enrolled By (Mobile/Email)',
                'Status', 'Payment Status', 'Amount (MVR)', 'Payment Ref',
                'Enrolled At', 'Created At', 'Last Decision', 'Decided By', 'Decided At',
            ]);

            foreach ($enrollments as $e) {
                Csv::put($handle, $this->exportRow($e));
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * One enrolment as the CSV lists it (the decision stamp last, STATUS §5ih).
     *
     * @return list<string|int|float|null>
     */
    private function exportRow(CourseEnrollment $e): array
    {
        $user = $e->creator;
        $mobile = $user?->mobile ?? $user?->contacts()->where('type', 'mobile')->value('value') ?? '';
        $email = $user?->email ?? $user?->contacts()->where('type', 'email')->value('value') ?? '';

        return [
            $e->id,
            $e->course?->title ?? '',
            $e->student?->full_name ?? '',
            $mobile ?: $email,
            $e->status,
            $e->payment_status,
            $e->payment?->amount ?? '',
            $e->payment?->merchant_reference ?? '',
            $e->enrolled_at?->format('Y-m-d H:i') ?? '',
            $e->created_at?->format('Y-m-d H:i') ?? '',
            $e->decision ?? '',
            $e->decider?->name ?? '',
            $e->decided_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    private function notifyUser(CourseEnrollment $enrollment, string $status): void
    {
        $enrollment->loadMissing(['creator', 'course', 'student']);

        $user = $enrollment->creator;
        $email = $user?->email ?? $user?->contacts()->where('type', 'email')->value('value');

        if ($email) {
            Mail::to($email)->queue(new EnrollmentStatusMail($enrollment, $status));
        }
    }

    private function sendActivationSms(CourseEnrollment $enrollment): void
    {
        try {
            $enrollment->loadMissing(['creator', 'course', 'student']);

            $user = $enrollment->creator;
            $mobile = $user?->contacts()->where('type', 'mobile')->whereNotNull('verified_at')->value('value');

            if (! $mobile) {
                return;
            }

            $studentName = $enrollment->student?->full_name ?? $user?->name ?? 'Student';
            $courseName = $enrollment->course?->title ?? 'the course';
            $fee = $enrollment->payment?->amount;
            $feeText = $fee ? ' Fee paid: MVR '.number_format($fee, 2).'.' : '';

            $feeText = $fee ? ' MVR '.number_format($fee, 2).' paid.' : '';
            $message = "Akuru: {$studentName} enrolled in {$courseName}.{$feeText} See you soon!";

            app(SmsSenderInterface::class)->sendSms($mobile, $message);
        } catch (\Throwable $e) {
            Log::error('Enrollment activation SMS failed: '.$e->getMessage());
        }
    }

    private function sendRejectionSms(CourseEnrollment $enrollment): void
    {
        try {
            $enrollment->loadMissing(['creator', 'course', 'student']);

            $user = $enrollment->creator;
            $mobile = $user?->contacts()->where('type', 'mobile')->whereNotNull('verified_at')->value('value');

            if (! $mobile) {
                return;
            }

            $studentName = $enrollment->student?->full_name ?? $user?->name ?? 'Student';
            $courseName = $enrollment->course?->title ?? 'the course';

            $message = "Akuru: Sorry, {$studentName}'s enrollment in {$courseName} was not approved. Contact us for details.";

            app(SmsSenderInterface::class)->sendSms($mobile, $message);
        } catch (\Throwable $e) {
            Log::error('Enrollment rejection SMS failed: '.$e->getMessage());
        }
    }

    public function payments(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admissions/Payments', app(ListAdminPaymentsAction::class)->execute($filters) + [
            'filters' => ['search' => (string) ($filters['search'] ?? ''), 'status' => (string) ($filters['status'] ?? '')],
            'statuses' => ListAdminPaymentsAction::STATUSES,
            'can_refund' => (bool) $request->user()?->can('payments.refund'),
            't' => trans('admin'),
        ]);
    }

    /** P4.4 (SPEC §49 payment reports): CSV of the filtered payments listing. */
    public function exportPayments(Request $request)
    {
        $payments = app(ListAdminPaymentsAction::class)->query($request->only(['search', 'status']))->get();

        return response()->streamDownload(function () use ($payments): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'reference', 'payer', 'student', 'amount', 'currency', 'status', 'provider', 'refunded_total', 'created_at']);
            foreach ($payments as $payment) {
                Csv::put($out, [
                    $payment->id,
                    $payment->local_id ?? $payment->merchant_reference,
                    $payment->user?->name ?? '',
                    $payment->student?->full_name ?? '',
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->currency,
                    $payment->status,
                    $payment->provider,
                    number_format((float) $payment->refunds->sum('amount'), 2, '.', ''),
                    $payment->created_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, 'payments.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * P4.4 (SPEC §49 "Admin can manually enroll or record payments"): record
     * money received outside the gateway against this enrollment. The
     * confirmed manual payment fires PaymentConfirmed — activation runs
     * through the same single listener path as a webhook.
     */
    public function recordManualPayment(Request $request, CourseEnrollment $enrollment)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
            // SPEC §38's "Payment method", which this form is the only place
            // anyone knows. It was captured as prose in `note` — the
            // placeholder literally read "e.g. cash at office" — so the
            // finance data could not tell cash from a bank transfer.
            'payment_method' => ['required', Rule::in(app(ListManualPaymentMethodsAction::class)->values())],
        ]);

        $payerUserId = $enrollment->student?->user_id
            ?? $enrollment->created_by_user_id
            ?? $request->user()->id;

        app(RecordManualPaymentAction::class)->execute(
            'course_enrollment',
            $enrollment->id,
            (int) $payerUserId,
            (float) $data['amount'],
            $data['note'] ?? null,
            $request->user()->id,
            (string) $data['payment_method'],
            [
                'course_id' => $enrollment->course_id,
                'course_offering_id' => $enrollment->course_offering_id,
                'unified_student_id' => $enrollment->unified_student_id,
                'metadata' => ['source' => 'admin_manual_payment', 'recorded_by' => $request->user()->id],
            ],
        );

        return back()->with('success', 'Manual payment recorded — enrollment updated.');
    }
}
