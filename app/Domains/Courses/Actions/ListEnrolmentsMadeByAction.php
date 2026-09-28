<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Every course enrolment one login made — their own and the ones they made
 * for a child on the website — newest first, with where each stands:
 * *My account* and *My enrolments* (docs/SIGN_IN_PLAN.md ID2b), which
 * replaced the old course portal's Blade pages.
 *
 * `state` is one word for the person reading it, worked out here once: a
 * pending enrolment is waiting either on its payment or on the office, the
 * same split *My learning* makes (`ListStudentDashboardAction::waiting`).
 * `payment` says whether money is owed, paid, or never was; the payment row
 * itself, and whether it has a receipt, is Finance's to say.
 */
class ListEnrolmentsMadeByAction
{
    /**
     * @return list<array{id: int, course: string, student: string, own: bool, state: string, payment: string, payment_id: ?int, date: ?string}>
     */
    public function execute(int $userId, ?int $limit = null): array
    {
        return CourseEnrollment::query()
            ->with(['course:id,title', 'student'])
            ->where('created_by_user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn (CourseEnrollment $enrollment): array => [
                'id' => (int) $enrollment->id,
                'course' => (string) ($enrollment->course?->title ?? '—'),
                'student' => (string) ($enrollment->student?->full_name ?? '—'),
                'own' => $enrollment->student !== null && (int) $enrollment->student->user_id === $userId,
                'state' => $this->state($enrollment),
                'payment' => match ((string) $enrollment->payment_status) {
                    'confirmed' => 'paid',
                    'pending', 'required' => 'unpaid',
                    default => 'none',
                },
                'payment_id' => $enrollment->payment_id !== null ? (int) $enrollment->payment_id : null,
                'date' => ($enrollment->enrolled_at ?? $enrollment->created_at)?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** How many enrolments the login made, for the account home's "all of them" link. */
    public function count(int $userId): int
    {
        return CourseEnrollment::query()->where('created_by_user_id', $userId)->count();
    }

    private function state(CourseEnrollment $enrollment): string
    {
        $status = (string) $enrollment->status;
        if ($status === 'pending') {
            return in_array($enrollment->payment_status, ['pending', 'required'], true) ? 'waiting_payment' : 'waiting_office';
        }

        return in_array($status, ['approved', 'active', 'completed', 'rejected', 'cancelled', 'suspended'], true) ? $status : 'waiting_office';
    }
}
