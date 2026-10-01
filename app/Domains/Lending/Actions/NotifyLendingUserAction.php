<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Mail\LendingNoticeMail;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * A lending notice to one person (L1): in the app always (category
 * `lending`, which the person may switch off), and by email and SMS as the
 * deploy allows. A borrower asks, a lender decides, a book changes hands —
 * each is one call. Failures to mail or text never fail the caller: the
 * in-app notice stands, as the Bookstore's does.
 */
class NotifyLendingUserAction
{
    public function execute(int $userId, string $title, string $message, ?string $href = null, string $event = 'lending'): void
    {
        if (! $this->inApp($userId, $title, $message, $href)) {
            return;
        }
        $person = $this->person($userId);
        if (config('lending.notices.email') && filter_var((string) ($person['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $this->email((string) $person['email'], (string) ($person['name'] ?? ''), $title, $message, $href);
        }
        $phone = trim((string) ($person['phone'] ?? ''));
        if (config('lending.notices.sms') && $phone !== '') {
            $this->sms($phone, $title, $message, $event);
        }
    }

    private function inApp(int $userId, string $title, string $message, ?string $href): bool
    {
        try {
            return app(SendUserNotificationAction::class)->execute($userId, $title, $message, ['category' => 'lending', 'href' => $href]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function email(string $address, string $name, string $title, string $message, ?string $href): void
    {
        $link = $href !== null ? url($href) : null;
        DB::afterCommit(function () use ($address, $name, $title, $message, $link): void {
            try {
                Mail::to($address)->queue(new LendingNoticeMail($name, $title, $message, $link));
            } catch (\Throwable) {
                // The in-app notice stands.
            }
        });
    }

    private function sms(string $phone, string $title, string $message, string $event): void
    {
        $body = mb_substr('Akuru: '.$title.'. '.$message, 0, (int) config('lending.notices.sms_max_length', 300));
        DB::afterCommit(function () use ($phone, $body, $event): void {
            try {
                $result = app(SmsSenderInterface::class)->sendSms($phone, $body, ['type' => 'lending', 'reference' => 'lending_'.$event]);
                if (($result['driver'] ?? null) !== 'log') {
                    app(RecordSmsReceiptAction::class)->execute([
                        'channel' => 'sms', 'type' => 'lending', 'reference' => 'lending_'.$event, 'phone' => $phone, 'body' => $body,
                        'driver' => $result['driver'] ?? 'gateway', 'success' => (bool) ($result['success'] ?? false),
                    ]);
                }
            } catch (\Throwable) {
                // As with email.
            }
        });
    }

    /**
     * @return array{name?: string, email?: ?string, phone?: ?string}
     */
    private function person(int $userId): array
    {
        $userModel = config('auth.providers.users.model');
        $row = $userModel::query()->whereKey($userId)->first(['id', 'name', 'email', 'phone']);

        return $row === null ? [] : ['name' => (string) $row->name, 'email' => $row->email, 'phone' => $row->phone];
    }
}
