<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use Illuminate\Database\Eloquent\Builder;

/**
 * The office's enrolment list (docs/ADMIN_PANEL.md; C9 slice 4, STATUS
 * §5jf): every application and enrolment, newest first, with the search
 * and the three filters the screen offers. One query serves the screen
 * and its CSV, so the download cannot drift from the list it claims to copy.
 */
class ListAdminEnrollmentsAction
{
    public const PER_PAGE = 20;

    public const STATUSES = ['pending', 'approved', 'active', 'rejected', 'suspended', 'completed', 'cancelled'];

    public const PAYMENT_STATUSES = ['not_required', 'required', 'pending', 'confirmed', 'refunded'];

    /**
     * @param  array<string, mixed>  $filters  course_id, status, payment_status, search
     */
    public function query(array $filters): Builder
    {
        $query = CourseEnrollment::with(['student', 'course', 'payment', 'creator', 'decider'])->latest()->orderByDesc('id');

        if ($courseId = (int) ($filters['course_id'] ?? 0)) {
            $query->where('course_id', $courseId);
        }
        if (in_array($status = (string) ($filters['status'] ?? ''), self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if (in_array($paymentStatus = (string) ($filters['payment_status'] ?? ''), self::PAYMENT_STATUSES, true)) {
            $query->where('payment_status', $paymentStatus);
        }
        // COMMERCE_PARITY_PLAN P3: by the learner's ID card — waiting, verified, rejected, or none sent.
        $idCard = (string) ($filters['id_card'] ?? '');
        if (in_array($idCard, ['pending', 'verified', 'rejected'], true)) {
            $query->whereIn('unified_student_id', app(IdentityVerificationAction::class)->learnerIdsWithStatus($idCard));
        } elseif ($idCard === 'none') {
            $sent = array_merge(...array_map(fn ($s) => app(IdentityVerificationAction::class)->learnerIdsWithStatus($s), ['pending', 'verified', 'rejected']));
            $query->whereNotIn('unified_student_id', $sent === [] ? [0] : $sent);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('student', function ($s) use ($search) {
                    $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('creator', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$search}%"));
                });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{enrollments: list<array<string, mixed>>, pagination: array<string, mixed>, total: int, courses: list<array{id: int, title: string}>}
     */
    public function execute(array $filters): array
    {
        $page = $this->query($filters)->paginate(self::PER_PAGE)->withQueryString();
        $cards = app(IdentityVerificationAction::class)->forStudents(collect($page->items())->pluck('unified_student_id')->filter()->map(fn ($id) => (int) $id)->all());

        return [
            'enrollments' => collect($page->items())->map(fn (CourseEnrollment $e) => [
                'id' => $e->id,
                'student' => $e->student?->full_name,
                'course' => $e->course?->title,
                'status' => (string) $e->status,
                'payment_status' => $e->payment_status !== null ? (string) $e->payment_status : null,
                'date' => $e->created_at?->format('d M Y'),
                'id_card' => $cards[(int) $e->unified_student_id]['status'] ?? 'none',
            ])->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'total' => $page->total(),
            'courses' => Course::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Course $c) => ['id' => (int) $c->id, 'title' => (string) $c->title])->values()->all(),
        ];
    }
}
