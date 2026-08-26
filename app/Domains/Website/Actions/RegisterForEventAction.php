<?php

namespace App\Domains\Website\Actions;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Offerings\Actions\EnforceSeatLimitAction;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterForEventAction
{
    /** @var list<string> */
    public const OCCUPYING_STATUSES = ['pending', 'confirmed', 'pending_parent'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): EventRegistration
    {
        $eventId = (int) ($data['event_id'] ?? 0);

        return DB::transaction(function () use ($data, $eventId): EventRegistration {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            $this->assertRegistrationOpen($event, (string) ($data['registration_source'] ?? 'website'));

            $studentId = isset($data['student_id']) && $data['student_id'] !== '' && $data['student_id'] !== null
                ? (int) $data['student_id']
                : null;
            $email = trim((string) ($data['email'] ?? ''));
            $name = trim((string) ($data['name'] ?? ''));

            if ($studentId !== null) {
                $student = DB::table('students')->where('id', $studentId)->first();
                if ($student === null) {
                    throw ValidationException::withMessages(['student_id' => 'Student not found.']);
                }
                if ($name === '') {
                    $name = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));
                }
                if ($email === '' && isset($data['fallback_email'])) {
                    $email = trim((string) $data['fallback_email']);
                }
            }

            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            if ($email === '') {
                throw ValidationException::withMessages(['email' => 'Email is required.']);
            }

            $this->assertNotAlreadyRegistered($eventId, $studentId, $email);

            $seat = app(EnforceSeatLimitAction::class)->execute(
                resourceTable: 'events',
                resourceId: $eventId,
                limitColumn: 'max_attendees',
                occupancyTable: 'event_registrations',
                foreignKey: 'event_id',
                occupyingStatuses: self::OCCUPYING_STATUSES,
                waitlistEnabledColumn: 'waitlist_enabled',
                fullMessage: 'This event has no remaining seats.',
            );

            $waitlisted = $seat['outcome'] === EnforceSeatLimitAction::OUTCOME_WAITLISTED;
            $status = $this->statusFor($event, $waitlisted, $studentId);

            $registration = EventRegistration::query()->create([
                'event_id' => $eventId,
                'student_id' => $studentId,
                'parent_user_id' => isset($data['parent_user_id']) ? (int) $data['parent_user_id'] : null,
                'name' => $name,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'organization' => $data['organization'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'waitlist_position' => $waitlisted ? $seat['waitlist_position'] : null,
                'academic_year_id' => $this->resolveAcademicYearId($event),
                'term_id' => isset($data['term_id']) && $data['term_id'] !== '' ? (int) $data['term_id'] : null,
                'registration_source' => $data['registration_source'] ?? 'website',
                'dietary_requirements' => (bool) ($data['dietary_requirements'] ?? false),
                'dietary_notes' => $data['dietary_notes'] ?? null,
                'transportation_needed' => (bool) ($data['transportation_needed'] ?? false),
                'transportation_notes' => $data['transportation_notes'] ?? null,
                'accommodation_needed' => (bool) ($data['accommodation_needed'] ?? false),
                'accommodation_notes' => $data['accommodation_notes'] ?? null,
                'amount_paid' => $event->registration_fee ?? 0,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
            ]);

            if (method_exists($registration, 'generateQrCode')) {
                $registration->generateQrCode();
            }

            $event->updateAttendeeCount();

            return $registration->fresh();
        });
    }

    private function assertRegistrationOpen(Event $event, string $source): void
    {
        if ($event->status !== 'published') {
            throw ValidationException::withMessages(['event_id' => 'This event is not open for registration.']);
        }

        if (! in_array($event->registration_type, ['required', 'optional'], true)) {
            throw ValidationException::withMessages(['event_id' => 'Registration is not available for this event.']);
        }

        if ($source === 'website' && ! $event->is_public) {
            throw ValidationException::withMessages(['event_id' => 'This event is not open for public registration.']);
        }

        $now = now();
        if ($event->registration_start && $event->registration_start->gt($now)) {
            throw ValidationException::withMessages(['event_id' => 'Registration has not opened yet.']);
        }

        $secondRoundOpen = $event->second_round_opens_at !== null && $event->second_round_opens_at->lte($now);
        if (! $secondRoundOpen && $event->registration_deadline && $event->registration_deadline->lt($now)) {
            throw ValidationException::withMessages(['event_id' => 'Registration has closed.']);
        }
    }

    private function assertNotAlreadyRegistered(int $eventId, ?int $studentId, string $email): void
    {
        $active = EventRegistration::query()
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['cancelled', 'no_show']);

        if ($studentId !== null) {
            $exists = (clone $active)->where('student_id', $studentId)->exists();
            if ($exists) {
                throw ValidationException::withMessages(['student_id' => 'This student is already registered for this event.']);
            }
        }

        $exists = (clone $active)->where('email', $email)->whereNull('student_id')->exists();
        if ($studentId === null && $exists) {
            throw ValidationException::withMessages(['email' => 'You are already registered for this event.']);
        }
    }

    private function statusFor(Event $event, bool $waitlisted, ?int $studentId): string
    {
        if ($waitlisted) {
            return 'waitlisted';
        }

        if ($event->requires_parent_confirmation && $studentId !== null) {
            return 'pending_parent';
        }

        return $event->registration_type === 'required' ? 'pending' : 'confirmed';
    }

    private function resolveAcademicYearId(Event $event): ?int
    {
        if ($event->academic_year_id) {
            return (int) $event->academic_year_id;
        }

        $current = app(ListAcademicYearsAction::class)->execute()->firstWhere('is_current', true);

        return isset($current['id']) ? (int) $current['id'] : null;
    }
}
