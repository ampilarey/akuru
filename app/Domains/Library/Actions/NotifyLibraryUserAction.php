<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Mail\LibraryNoticeMail;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * LIBRARY_PLAN §41, the Library's notifications, through Notifications'
 * one writer (rule 3: another domain's Action, never its model). In-app
 * always: the portal's Notifications page lists them with a link. Category
 * `library`, which a person can switch off in their preferences.
 *
 *  - writer: application decided, submission received, changes requested,
 *    rejected, published, new sale, payout decided;
 *  - reader: access granted after a purchase;
 *  - office: new writer application, new submission.
 *
 * STATUS §5lq: a call that names its **event** — one of
 * `library.notices.events`, the decisions and the money — may also go by
 * email and by SMS, as the office's two switches on the settings screen
 * allow (both off by default). Someone who switched library notices off
 * gets neither: the in-app preference is the one switch a person has.
 * Email is queued; SMS goes through Notifications' SMS contract, which logs
 * instead of sending wherever `SMS_LIVE` is off. Both wait for the caller's
 * transaction to commit. Reader nudges and office alerts stay in-app.
 *
 * Every call is fire-and-forget: a notification that fails must never fail
 * the decision it describes.
 */
class NotifyLibraryUserAction
{
    public function execute(int $userId, string $title, string $message, ?string $href = null, ?string $event = null): void
    {
        if (! $this->inApp($userId, $title, $message, $href)) {
            return;
        }
        if ($event === null || ! in_array($event, (array) config('library.notices.events'), true)) {
            return;
        }
        $settings = app(ResolveLibrarySettingAction::class);
        $email = (bool) $settings->execute('notices_email');
        $sms = (bool) $settings->execute('notices_sms');
        if (! $email && ! $sms) {
            return;
        }
        $person = $this->person($userId);
        if ($email) {
            $this->email($person, $title, $message, $href);
        }
        if ($sms && ! empty($person['phone'])) {
            $this->sms((string) $person['phone'], $title, $message, $event);
        }
    }

    /** Everyone who runs the Library: the holders of `library.manage`. In-app only. */
    public function office(string $title, string $message, ?string $href = null): void
    {
        $userModel = config('auth.providers.users.model');
        try {
            $ids = $userModel::query()->permission('library.manage')->pluck('id');
        } catch (\Throwable) {
            return;
        }
        foreach ($ids as $id) {
            $this->inApp((int) $id, $title, $message, $href);
        }
    }

    private function inApp(int $userId, string $title, string $message, ?string $href): bool
    {
        try {
            return app(SendUserNotificationAction::class)->execute($userId, $title, $message, [
                'category' => 'library',
                'href' => $href,
            ]) !== null;
        } catch (\Throwable) {
            // Recorded nowhere on purpose: the caller's transaction matters more.
            return false;
        }
    }

    /** @param  array{name?: string, email?: ?string, phone?: ?string}  $person */
    private function email(array $person, string $title, string $message, ?string $href): void
    {
        $address = trim((string) ($person['email'] ?? ''));
        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $link = $href !== null ? url($href) : null;
        DB::afterCommit(function () use ($address, $person, $title, $message, $link): void {
            try {
                Mail::to($address)->queue(new LibraryNoticeMail((string) ($person['name'] ?? ''), $title, $message, $link));
            } catch (\Throwable) {
                // The in-app notice stands; a mail failure never fails the decision.
            }
        });
    }

    private function sms(string $phone, string $title, string $message, string $event): void
    {
        $body = mb_substr('Akuru Library: '.$title.'. '.$message, 0, (int) config('library.notices.sms_max_length', 300));
        DB::afterCommit(function () use ($phone, $body, $event): void {
            try {
                $result = app(SmsSenderInterface::class)->sendSms($phone, $body, ['type' => 'library', 'reference' => 'library_'.$event]);
                if (($result['driver'] ?? null) !== 'log') {
                    app(RecordSmsReceiptAction::class)->execute([
                        'channel' => 'sms', 'type' => 'library', 'reference' => 'library_'.$event, 'phone' => $phone, 'body' => $body,
                        'driver' => $result['driver'] ?? 'gateway', 'success' => (bool) ($result['success'] ?? false),
                    ]);
                }
            } catch (\Throwable) {
                // As with email: the in-app notice stands.
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
