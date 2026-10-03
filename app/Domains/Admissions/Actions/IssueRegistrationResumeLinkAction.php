<?php

namespace App\Domains\Admissions\Actions;

use App\Domains\Admissions\Models\RegistrationFlow;
use App\Domains\Admissions\Notifications\RegistrationResumeLinkNotification;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Send a family a link to finish a course registration later (BACKLOG C16
 * slice N5, STATUS §5oa; OWNER_ACTIONS 14, decided 2026-10-03: build it,
 * short-lived and single-use, resuming the form only).
 *
 * The flow row (`registration_flows`, with no writer until now) keeps the
 * courses and term they chose; the link carries the flow's uuid and a
 * 40-character token whose sha256 is stored, never the token. It is good
 * for a day and spent on first use. Opening it brings the courses back and
 * asks for a fresh code at their contact — the link never signs anyone in.
 *
 * The person and the contact arrive as ids and strings, not as the models
 * of the domain that owns them (rule 3): this action needs a user id to key
 * the flow on and a contact's id, kind and address to send to, no more.
 */
class IssueRegistrationResumeLinkAction
{
    public const HOURS_VALID = 24;

    public function __construct(private readonly SmsSenderInterface $sms) {}

    /**
     * @param  string  $contactType  `mobile` or `email`
     * @param  array{course_ids: list<int>, term_id?: int|null, checkout_flow?: string|null, course_titles?: list<string>}  $payload
     * @return array{flow: RegistrationFlow, url: string}
     */
    public function execute(int $userId, int $contactId, string $contactType, string $contactValue, array $payload): array
    {
        $token = Str::random(40);
        $flow = RegistrationFlow::latestActiveForUser($userId) ?? new RegistrationFlow(['user_id' => $userId]);
        $flow->forceFill([
            'user_id' => $userId,
            'contact_id' => $contactId,
            'status' => 'selecting_students',
            'payload' => [
                'course_ids' => array_values(array_map('intval', $payload['course_ids'] ?? [])),
                'term_id' => $payload['term_id'] ?? null,
                'checkout_flow' => $payload['checkout_flow'] ?? null,
            ],
            'resume_token_hash' => hash('sha256', $token),
            'resume_sent_at' => now(),
            'resumed_at' => null,
            'expires_at' => now()->addHours(self::HOURS_VALID),
        ])->save();

        $url = route('courses.register.resume', ['flow' => $flow->uuid, 't' => $token]);
        $titles = array_values(array_filter(array_map('strval', $payload['course_titles'] ?? [])));

        if ($contactType === 'mobile') {
            $this->sms->sendSms(
                $contactValue,
                'Akuru Institute: finish your course registration here: '.$url.' The link works once, for '.self::HOURS_VALID.' hours; you will be asked for a code.',
                ['purpose' => 'registration_resume'],
            );
        } else {
            Notification::route('mail', $contactValue)
                ->notify(new RegistrationResumeLinkNotification($url, $titles, self::HOURS_VALID));
        }

        return ['flow' => $flow->refresh(), 'url' => $url];
    }
}
