<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Mail\BookshopNoticeMail;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The bookstore's notices, through Notifications' one writer (rule 3).
 * In-app always; category `shop`, which a person can switch off. Every call
 * is fire-and-forget: a notice that fails never fails the order it describes.
 *
 *  - customer: order paid, bank transfer confirmed or rejected, dispatched…;
 *  - vendor members: a paid order to fulfil, low stock, a return…;
 *  - office (`bookshop.manage`): a slip to confirm, an order needing attention.
 *
 * B8 (§4 "Notices in app and by email (SMS where the office enables it)",
 * §5 and §7 "email/SMS switches"): a call that names its **event** may also
 * go by email or SMS. The office's four switches gate each channel for
 * customers and for shops; a shop then picks per event. Someone who switched
 * shop notices off gets neither — the in-app preference is the one switch
 * a person has. Email is queued; SMS goes through Notifications' SMS
 * contract, which logs instead of sending wherever `SMS_LIVE` is off.
 * Both wait for the caller's transaction to commit.
 */
class NotifyBookshopUserAction
{
    public const SETTING_PREFIX = 'bookshop_notices_';

    public function execute(int $userId, string $title, string $message, ?string $href = null, ?string $event = null): void
    {
        if (! $this->inApp($userId, $title, $message, $href)) {
            return;
        }
        if ($event === null || ! in_array($event, (array) config('bookshop.notices.customer_events'), true)) {
            return;
        }
        $office = self::officeSwitches();
        if ($office['customer_email']) {
            $this->email($userId, $title, $message, $href);
        }
        if ($office['customer_sms']) {
            $phone = $this->person($userId)['phone'] ?? null;
            if ($phone) {
                $this->sms($phone, $title, $message, $event);
            }
        }
    }

    /** Every member of a vendor, owners first; by email or SMS as the shop chose (B8). */
    public function vendor(int $vendorId, string $title, string $message, ?string $href = null, ?string $event = null): void
    {
        $ids = VendorMember::query()->where('vendor_id', $vendorId)
            ->orderByRaw('case when role = ? then 0 else 1 end', [VendorMemberRole::Owner->value])
            ->pluck('user_id');
        $channels = $event !== null ? self::vendorChannels($vendorId, $event) : ['email' => false, 'sms' => false];
        foreach ($ids as $id) {
            if ($this->inApp((int) $id, $title, $message, $href) && $channels['email']) {
                $this->email((int) $id, $title, $message, $href);
            }
        }
        if ($channels['sms']) {
            // The shop's own number, once — not every member's.
            $phone = trim((string) Vendor::query()->whereKey($vendorId)->value('contact_phone'));
            if ($phone !== '') {
                $this->sms($phone, $title, $message, $event);
            }
        }
    }

    /** Everyone who runs the bookstore: the holders of `bookshop.manage`. In-app only. */
    public function office(string $title, string $message, ?string $href = null): void
    {
        $userModel = config('auth.providers.users.model');
        try {
            $ids = $userModel::query()->permission('bookshop.manage')->pluck('id');
        } catch (\Throwable) {
            return;
        }
        foreach ($ids as $id) {
            $this->inApp((int) $id, $title, $message, $href);
        }
    }

    /**
     * The office's switches, defaults from config.
     *
     * @return array{customer_email: bool, customer_sms: bool, vendor_email: bool, vendor_sms: bool}
     */
    public static function officeSwitches(): array
    {
        $defaults = (array) config('bookshop.notices.office_defaults');
        $keys = [];
        foreach ($defaults as $key => $default) {
            $keys[self::SETTING_PREFIX.$key] = null;
        }
        try {
            $stored = app(SettingsRepositoryInterface::class)->many($keys);
        } catch (\Throwable) {
            $stored = [];
        }
        $out = [];
        foreach ($defaults as $key => $default) {
            $value = $stored[self::SETTING_PREFIX.$key] ?? null;
            $out[$key] = $value === null || $value === '' ? (bool) $default : in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
        }

        /** @var array{customer_email: bool, customer_sms: bool, vendor_email: bool, vendor_sms: bool} $out */
        return $out;
    }

    /**
     * What a shop chose for each event, over the defaults.
     *
     * @return array<string, array{email: bool, sms: bool}>
     */
    public static function vendorSettings(?array $stored): array
    {
        $out = [];
        foreach ((array) config('bookshop.notices.vendor_defaults') as $event => $default) {
            $out[$event] = [
                'email' => (bool) ($stored[$event]['email'] ?? $default['email'] ?? false),
                'sms' => (bool) ($stored[$event]['sms'] ?? $default['sms'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * The shop's choice for one event, and-ed with the office's switches.
     *
     * @return array{email: bool, sms: bool}
     */
    public static function vendorChannels(int $vendorId, string $event): array
    {
        $stored = Vendor::query()->whereKey($vendorId)->first(['id', 'notice_settings'])?->notice_settings;
        $settings = self::vendorSettings(is_array($stored) ? $stored : null);
        $office = self::officeSwitches();

        return [
            'email' => $office['vendor_email'] && ($settings[$event]['email'] ?? false),
            'sms' => $office['vendor_sms'] && ($settings[$event]['sms'] ?? false),
        ];
    }

    private function inApp(int $userId, string $title, string $message, ?string $href): bool
    {
        try {
            return app(SendUserNotificationAction::class)->execute($userId, $title, $message, ['category' => 'shop', 'href' => $href]) !== null;
        } catch (\Throwable) {
            // Recorded nowhere on purpose: the caller's transaction matters more.
            return false;
        }
    }

    private function email(int $userId, string $title, string $message, ?string $href): void
    {
        $person = $this->person($userId);
        $address = trim((string) ($person['email'] ?? ''));
        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $link = $href !== null ? url($href) : null;
        DB::afterCommit(function () use ($address, $person, $title, $message, $link): void {
            try {
                Mail::to($address)->queue(new BookshopNoticeMail((string) ($person['name'] ?? ''), $title, $message, $link));
            } catch (\Throwable) {
                // The in-app notice stands; a mail failure never fails the order.
            }
        });
    }

    private function sms(string $phone, string $title, string $message, string $event): void
    {
        $body = mb_substr('Akuru Bookstore: '.$title.'. '.$message, 0, (int) config('bookshop.notices.sms_max_length', 300));
        DB::afterCommit(function () use ($phone, $body, $event): void {
            try {
                $result = app(SmsSenderInterface::class)->sendSms($phone, $body, ['type' => 'bookshop', 'reference' => 'bookshop_'.$event]);
                if (($result['driver'] ?? null) !== 'log') {
                    app(RecordSmsReceiptAction::class)->execute([
                        'channel' => 'sms', 'type' => 'bookshop', 'reference' => 'bookshop_'.$event, 'phone' => $phone, 'body' => $body,
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
