<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\VendorApplication;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * "Open a shop in the Akuru Bookstore" (BOOKSHOP_PLAN §3, slice B9a:
 * public vendor onboarding, apply → approve, like writers). A signed-in
 * person tells the office about their shop and accepts the Vendor
 * Agreement; the office decides. One waiting application per person. The
 * office can close the form (a setting), and it says so.
 */
class ApplyToSellAction
{
    public function isOpen(): bool
    {
        $value = app(SettingsRepositoryInterface::class)->get((string) config('bookshop.onboarding.setting_key'));
        if ($value === null || $value === '') {
            return (bool) config('bookshop.onboarding.open_by_default', true);
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    public function setOpen(bool $open): void
    {
        app(SetSettingAction::class)->execute((string) config('bookshop.onboarding.setting_key'), $open, 'boolean', 'bookshop', 'Bookstore: shop applications open');
    }

    /** In any shop at all, even a suspended one (whose owner gets the portal's own refusal, not this form). */
    public function belongsToAShop(int $userId): bool
    {
        return VendorMember::query()->where('user_id', $userId)->exists();
    }

    /**
     * The person's latest application, for the page.
     *
     * @return array<string, mixed>|null
     */
    public function latest(int $userId): ?array
    {
        $a = VendorApplication::query()->where('user_id', $userId)->with('vendor:id,name,slug')->latest('id')->first();

        return $a === null ? null : [
            'id' => $a->id,
            'shop_name' => $a->shop_name,
            'status' => $a->status,
            'submitted_at' => $a->created_at?->toDateString(),
            'decided_at' => $a->decided_at?->toDateString(),
            'decision_note' => $a->decision_note,
            'vendor' => $a->vendor ? ['name' => $a->vendor->name, 'slug' => $a->vendor->slug] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): VendorApplication
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages(['shop_name' => __('shop.error_applications_closed')]);
        }
        if (VendorApplication::query()->where('user_id', $userId)->where('status', VendorApplication::PENDING)->exists()) {
            throw ValidationException::withMessages(['shop_name' => __('shop.error_application_pending')]);
        }
        if (empty($data['agreement'])) {
            throw ValidationException::withMessages(['agreement' => __('shop.error_agreement_required')]);
        }

        $application = VendorApplication::query()->create([
            'user_id' => $userId,
            'shop_name' => trim((string) $data['shop_name']),
            'legal_name' => $this->clean($data['legal_name'] ?? null),
            'tin' => $this->clean($data['tin'] ?? null),
            'contact_email' => trim((string) $data['contact_email']),
            'contact_phone' => trim((string) $data['contact_phone']),
            'island' => trim((string) $data['island']),
            'what_they_sell' => trim((string) $data['what_they_sell']),
            'link' => $this->clean($data['link'] ?? null),
            'agreement_accepted_at' => now(),
            'status' => VendorApplication::PENDING,
        ]);

        app(NotifyBookshopUserAction::class)->office(
            __('shop.notice_application_title'),
            __('shop.notice_application_body', ['shop' => $application->shop_name, 'island' => $application->island]),
            '/admin/bookshop',
        );

        return $application;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
