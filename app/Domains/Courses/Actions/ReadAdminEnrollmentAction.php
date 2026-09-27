<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * One enrolment as the office reads it (docs/ADMIN_PANEL.md; C9 slice 5,
 * STATUS §5jg): the enrolment, the student and their guardians, the payment
 * if there is one, the access window, and which decisions the page may
 * offer — worked out here, so the screen renders what it is given rather
 * than deciding for itself. The decision stamp is composed here too, in the
 * reader's language, so the page and its test read one string.
 */
class ReadAdminEnrollmentAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(CourseEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['student.guardians', 'course', 'payment', 'creator', 'decider']);
        $student = $enrollment->student;
        $payment = $enrollment->payment;
        $status = (string) $enrollment->status;

        return [
            'id' => $enrollment->id,
            'course' => $enrollment->course?->title,
            'status' => $status,
            'payment_status' => $enrollment->payment_status !== null ? (string) $enrollment->payment_status : null,
            'enrolled_at' => $enrollment->enrolled_at?->format('d M Y, H:i'),
            'registered_by' => $enrollment->creator?->name,
            'decision' => $enrollment->decision,
            'last_decision' => $this->stamp($enrollment),
            'student' => $student === null ? null : [
                'name' => $student->full_name,
                'date_of_birth' => $student->date_of_birth?->format('d M Y'),
                'gender' => $student->gender,
                'national_id' => $student->national_id,
                'guardians' => $student->guardians->map(fn ($g) => [
                    'name' => $g->name ?? $g->full_name ?? null,
                    'relationship' => $g->pivot->relationship ?? null,
                ])->values()->all(),
            ],
            'payment' => $payment === null ? null : [
                'reference' => $payment->merchant_reference,
                'amount' => number_format((float) $payment->amount, 2),
                'currency' => (string) $payment->currency,
                'status' => (string) $payment->status,
                'paid_at' => $payment->paid_at?->format('d M Y, H:i'),
            ],
            'access_starts_at' => $enrollment->access_starts_at?->format('Y-m-d\TH:i'),
            'access_ends_at' => $enrollment->access_ends_at?->format('Y-m-d\TH:i'),
            // The fee the office most likely received, offered as the default amount.
            'suggested_amount' => (string) ($enrollment->course?->registration_fee_amount ?: ($enrollment->course?->fee ?: '')),
            'can_activate' => $status !== 'active',
            'can_reject' => $status !== 'rejected',
            'can_reinstate' => $status === 'suspended',
            'can_suspend' => in_array($status, ['pending', 'approved', 'active', 'completed'], true),
            // P4.4: money received outside the gateway, only while it is still owed.
            'awaits_payment' => in_array($enrollment->payment_status, ['pending', 'required'], true),
        ];
    }

    /**
     * Who made the last decision, and when (STATUS §5ih). Empty for a place
     * the payment webhook activated, or one decided before the stamp existed.
     */
    private function stamp(CourseEnrollment $enrollment): string
    {
        if ($enrollment->decided_at === null) {
            return trans('admin.enrolment_no_decision');
        }
        $decision = (string) ($enrollment->decision ?? 'decided');
        $label = trans()->has('admin.enrolment_decision_'.$decision)
            ? trans('admin.enrolment_decision_'.$decision)
            : ucfirst(str_replace('_', ' ', $decision));

        return trans('admin.enrolment_decision_stamp', [
            'decision' => $label,
            'who' => $enrollment->decider?->name ?? trans('admin.enrolment_removed_account'),
            'when' => $enrollment->decided_at->format('d M Y, H:i'),
        ]);
    }
}
