<?php

namespace App\Domains\Admissions\Actions;

use App\Domains\Admissions\DTOs\FreeEnrollmentNoticeData;
use App\Domains\Admissions\Events\FreeEnrollmentConfirmed;
use App\Domains\Identity\Actions\ResolveUserNoticeContactAction;
use Illuminate\Support\Facades\DB;

/**
 * Announce the enrollments from a registration that needed no payment.
 *
 * SPEC §41: "Cross-domain side effects must use events/listeners … Enrollment
 * code must not directly call notification implementation classes." Before
 * this, `CourseRegistrationController` held two `protected` methods that
 * queued two Mailables and sent an SMS — mail composed inside a controller,
 * which is rule 5 as well as §41.
 *
 * **Which enrollments count is business logic, so it lives here**, not in the
 * controller: an enrollment is announced now precisely when its
 * `payment_status` is `not_required`. A paid one waits for the webhook, because
 * rule 12 says access and announcements follow confirmed money, never a
 * redirect back from the gateway.
 *
 * **Dispatch is deferred to after commit.** The registration writes its
 * enrollments inside a transaction; a queued mail or an SMS raised inside it
 * would be announcing a row that can still disappear. `DB::afterCommit()`
 * defers inside a transaction and runs immediately outside one, so this is
 * correct whether or not the caller wrapped it.
 *
 * @param  iterable<mixed>  $enrollments  the registration's created enrollments,
 *                                        paid and free mixed together
 */
class AnnounceFreeEnrollmentsAction
{
    public function execute(int $payerUserId, iterable $enrollments): void
    {
        $free = [];

        foreach ($enrollments as $enrollment) {
            if ($enrollment && $enrollment->payment_status === 'not_required') {
                $free[] = $enrollment;
            }
        }

        if ($free === []) {
            return;
        }

        // Through Identity's own Action: asking the User model directly from
        // here would be rule 3's boundary, and it is also read once for the
        // payer rather than once per enrollment — and once here rather than
        // inside a Blade template, which is where the admin notice used to run
        // these queries, while rendering, on a queue.
        $payer = app(ResolveUserNoticeContactAction::class)->execute($payerUserId);

        foreach ($free as $enrollment) {
            $enrollment->loadMissing(['course', 'student', 'creator']);

            $notice = new FreeEnrollmentNoticeData(
                enrollmentId: (int) $enrollment->id,
                courseTitle: (string) ($enrollment->course?->title ?? 'Akuru Institute'),
                studentName: (string) ($enrollment->student?->full_name ?? $payer['name']),
                enrolledByName: (string) ($enrollment->creator?->name ?? $payer['name']),
                enrolledAtLabel: $enrollment->enrolled_at?->format('d M Y, H:i'),
                payerName: $payer['name'],
                payerEmail: $payer['email'],
                payerMobile: $payer['mobile'],
            );

            DB::afterCommit(fn () => event(new FreeEnrollmentConfirmed($notice)));
        }
    }
}
