<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §11.7's `access_starts_at` and `access_ends_at`, which existed nowhere
 * until this slice — so access to a course had no time dimension and an
 * enrolment could neither begin later nor run out.
 *
 * **Null means unbounded at that end**, which is what every row created before
 * this slice holds. A student whose enrolment has no window keeps exactly the
 * access they had, and nothing needed backfilling.
 *
 * The refusal says **which** end failed. "Not yet" and "expired" are different
 * facts and lead to different actions — wait, or go and pay — and a single
 * "no access" message would be the same invisible refusal this codebase has
 * produced in §36's upload and §6.3's recorder.
 */
class ResolveEnrollmentAccessWindowAction
{
    public const PENDING = 'not_started';

    public const EXPIRED = 'expired';

    /**
     * @return array{open: bool, reason: string|null, message: string|null, starts_at: string|null, ends_at: string|null}
     */
    public function execute(?CourseEnrollment $enrollment): array
    {
        $startsAt = $enrollment?->access_starts_at;
        $endsAt = $enrollment?->access_ends_at;
        $now = now();

        $reason = null;
        if ($startsAt !== null && $now->lt($startsAt)) {
            $reason = self::PENDING;
        } elseif ($endsAt !== null && $now->gt($endsAt)) {
            $reason = self::EXPIRED;
        }

        return [
            'open' => $reason === null,
            'reason' => $reason,
            'message' => $reason === null ? null : $this->message($reason, $startsAt, $endsAt),
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
        ];
    }

    /**
     * Validate and normalise a window before it is stored.
     *
     * A window that ends before it begins is not a narrow window, it is one
     * nothing can ever satisfy — and a student locked out by a typo has no way
     * to tell that from a deliberate block.
     *
     * @param  array<string, mixed>  $data
     * @return array{access_starts_at: string|null, access_ends_at: string|null}
     */
    public function validated(array $data): array
    {
        $startsAt = $this->timestamp($data['access_starts_at'] ?? null);
        $endsAt = $this->timestamp($data['access_ends_at'] ?? null);

        if ($startsAt !== null && $endsAt !== null && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages([
                'access_ends_at' => ['Access cannot end before it starts.'],
            ]);
        }

        return [
            'access_starts_at' => $startsAt?->toDateTimeString(),
            'access_ends_at' => $endsAt?->toDateTimeString(),
        ];
    }

    private function message(string $reason, mixed $startsAt, mixed $endsAt): string
    {
        return match ($reason) {
            self::PENDING => 'Your access to this course starts on '.$startsAt->toDayDateTimeString().'.',
            self::EXPIRED => 'Your access to this course ended on '.$endsAt->toDayDateTimeString().'.',
            default => 'This course is not open to you at the moment.',
        };
    }

    private function timestamp(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // The app timezone is Indian/Maldives (CLAUDE.md conventions); a
            // date typed by an administrator is in their day, not in UTC.
            return \Illuminate\Support\Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'access_starts_at' => ['That is not a date this can read.'],
            ]);
        }
    }
}
