<?php

namespace App\Domains\Admissions\Http\Controllers;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Http\Controllers\Controller;
use App\Mail\EnrollmentStatusMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminEnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = CourseEnrollment::with(['student', 'course', 'payment', 'creator'])
            ->latest();

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($s) use ($search) {
                    $s->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('creator', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
                });
            });
        }

        $enrollments = $query->paginate(20)->withQueryString();
        $courses = Course::orderBy('title')->get(['id', 'title']);

        return view('admin.enrollments.index', compact('enrollments', 'courses'));
    }

    public function show(CourseEnrollment $enrollment)
    {
        $enrollment->load(['student.guardians', 'course', 'payment.items.course', 'creator']);

        return view('admin.enrollments.show', compact('enrollment'));
    }

    public function activate(CourseEnrollment $enrollment)
    {
        $enrollment->update([
            'status' => 'active',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
        ]);

        $this->notifyUser($enrollment, 'active');
        $this->sendActivationSms($enrollment);

        return back()->with('success', 'Enrollment activated and student notified via SMS.');
    }

    public function reject(CourseEnrollment $enrollment)
    {
        $enrollment->update(['status' => 'rejected']);

        $this->notifyUser($enrollment, 'rejected');
        $this->sendRejectionSms($enrollment);

        return back()->with('success', 'Enrollment rejected and student notified.');
    }

    public function export(Request $request)
    {
        $query = CourseEnrollment::with(['student', 'course', 'payment', 'creator'])
            ->latest();

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($s) use ($search) {
                    $s->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('creator', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
                });
            });
        }

        $enrollments = $query->get();

        $filename = 'enrollments-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($enrollments) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Course', 'Student Name', 'Enrolled By (Mobile/Email)',
                'Status', 'Payment Status', 'Amount (MVR)', 'Payment Ref',
                'Enrolled At', 'Created At',
            ]);

            foreach ($enrollments as $e) {
                $user = $e->creator;
                $mobile = $user?->mobile ?? $user?->contacts()->where('type', 'mobile')->value('value') ?? '';
                $email = $user?->email ?? $user?->contacts()->where('type', 'email')->value('value') ?? '';
                $contact = $mobile ?: $email;

                fputcsv($handle, [
                    $e->id,
                    $e->course?->title ?? '',
                    $e->student?->full_name ?? '',
                    $contact,
                    $e->status,
                    $e->payment_status,
                    $e->payment?->amount ?? '',
                    $e->payment?->merchant_reference ?? '',
                    $e->enrolled_at?->format('Y-m-d H:i') ?? '',
                    $e->created_at?->format('Y-m-d H:i') ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
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
            \Illuminate\Support\Facades\Log::error('Enrollment activation SMS failed: '.$e->getMessage());
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
            \Illuminate\Support\Facades\Log::error('Enrollment rejection SMS failed: '.$e->getMessage());
        }
    }

    public function payments(Request $request)
    {
        $payments = $this->paymentsQuery($request)->paginate(20)->withQueryString();

        return view('admin.enrollments.payments', compact('payments'));
    }

    /** P4.4 (SPEC §49 payment reports): CSV of the filtered payments listing. */
    public function exportPayments(Request $request)
    {
        $payments = $this->paymentsQuery($request)->get();

        return response()->streamDownload(function () use ($payments): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'reference', 'payer', 'student', 'amount', 'currency', 'status', 'provider', 'refunded_total', 'created_at']);
            foreach ($payments as $payment) {
                fputcsv($out, [
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
        ]);

        $payerUserId = $enrollment->student?->user_id
            ?? $enrollment->created_by_user_id
            ?? $request->user()->id;

        app(\App\Domains\Finance\Actions\RecordManualPaymentAction::class)->execute(
            'course_enrollment',
            $enrollment->id,
            (int) $payerUserId,
            (float) $data['amount'],
            $data['note'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Manual payment recorded — enrollment updated.');
    }

    private function paymentsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Payment::with(['user', 'student', 'items.course', 'refunds'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('merchant_reference', 'like', "%{$search}%")
                    ->orWhere('local_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('student', function ($s) use ($search) {
                        $s->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
