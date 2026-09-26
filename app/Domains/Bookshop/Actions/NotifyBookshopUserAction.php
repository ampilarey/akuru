<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Notifications\Actions\SendUserNotificationAction;

/**
 * The bookstore's notices, through Notifications' one writer (rule 3).
 * In-app; category `shop`, which a person can switch off. Every call is
 * fire-and-forget: a notice that fails never fails the order it describes.
 *
 *  - customer: order paid, bank transfer confirmed or rejected;
 *  - vendor members: a paid order to fulfil, an order needing attention;
 *  - office (`bookshop.manage`): a slip to confirm, an order needing attention.
 */
class NotifyBookshopUserAction
{
    public function execute(int $userId, string $title, string $message, ?string $href = null): void
    {
        try {
            app(SendUserNotificationAction::class)->execute($userId, $title, $message, ['category' => 'shop', 'href' => $href]);
        } catch (\Throwable) {
            // Recorded nowhere on purpose: the caller's transaction matters more.
        }
    }

    /** Every member of a vendor, owners first. */
    public function vendor(int $vendorId, string $title, string $message, ?string $href = null): void
    {
        $ids = VendorMember::query()->where('vendor_id', $vendorId)
            ->orderByRaw('case when role = ? then 0 else 1 end', [VendorMemberRole::Owner->value])
            ->pluck('user_id');
        foreach ($ids as $id) {
            $this->execute((int) $id, $title, $message, $href);
        }
    }

    /** Everyone who runs the bookstore: the holders of `bookshop.manage`. */
    public function office(string $title, string $message, ?string $href = null): void
    {
        $userModel = config('auth.providers.users.model');
        try {
            $ids = $userModel::query()->permission('bookshop.manage')->pluck('id');
        } catch (\Throwable) {
            return;
        }
        foreach ($ids as $id) {
            $this->execute((int) $id, $title, $message, $href);
        }
    }
}
