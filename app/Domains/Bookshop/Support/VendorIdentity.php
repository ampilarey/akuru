<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P2: a shop sells only once the office has checked an
 * owner's identity card (front and back). Until then it may set up — its
 * products as drafts, its storefront, its delivery — but not put a product
 * on sale or ask for a payout. Products already on sale stay on sale.
 */
final class VendorIdentity
{
    public static function verified(int $vendorId): bool
    {
        $owners = VendorMember::query()->where('vendor_id', $vendorId)
            ->where('role', VendorMemberRole::Owner->value)->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        return app(IdentityVerificationAction::class)->anyVerified($owners, 'vendor');
    }

    /** @throws ValidationException */
    public static function require(int $vendorId, string $field = 'status'): void
    {
        if (! self::verified($vendorId)) {
            throw ValidationException::withMessages([$field => __('account.id_needed_vendor')]);
        }
    }
}
