<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EnrollmentStatusMail;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EnrollmentController extends Controller
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
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->paginate(20)->withQueryString();
        $courses     = Course::orderBy('title')->get(['id', 'title']);

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
            'status'      => 'active',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
        ]);

        $this->notifyUser($enrollment, 'active');

        return back()->with('success', 'Enrollment activated and student notified.');
    }

    public function reject(CourseEnrollment $enrollment)
    {
        $enrollment->update(['status' => 'rejected']);

        $this->notifyUser($enrollment, 'rejected');

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
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->get();

        $filename = 'enrollments-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
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
                $user   = $e->creator;
                $mobile = $user?->mobile ?? $user?->contacts()->where('type', 'mobile')->value('value') ?? '';
                $email  = $user?->email  ?? $user?->contacts()->where('type', 'email')->value('value')  ?? '';
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

        $user  = $enrollment->creator;
        $email = $user?->email ?? $user?->contacts()->where('type', 'email')->value('value');

        if ($email) {
            Mail::to($email)->queue(new EnrollmentStatusMail($enrollment, $status));
        }
    }

    public function payments(Request $request)
    {
        $query = Payment::with(['user', 'student', 'items.course'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('merchant_reference', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('student', function ($s) use ($search) {
                      $s->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.enrollments.payments', compact('payments'));
    }
}
