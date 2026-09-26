<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\SectionTypes;
use Illuminate\Validation\ValidationException;

/**
 * The office moderates a storefront (BOOKSHOP_PLAN §6.6): it may **require
 * changes** (a note the designer shows until the next publish), **take a
 * storefront down** (the public sees the plain page and publishing is
 * refused until the hold is lifted), **lift** the hold, and **lock section
 * types** for one shop (a locked type is refused on save and on publish).
 * Every step clears the published storefront's cache, so the public page
 * changes at once.
 */
class ModerateStorefrontAction
{
    public const ACTIONS = ['require_changes', 'hold', 'lift', 'lock'];

    /**
     * @param  list<string>  $lockedTypes
     */
    public function execute(int $vendorId, string $action, int $userId, ?string $note = null, array $lockedTypes = []): VendorStorefront
    {
        $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $vendorId]);
        $note = trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 1000) : null;
        match ($action) {
            'require_changes' => $storefront->update(['moderation_note' => $note ?? throw ValidationException::withMessages(['note' => __('shop.error_moderation_note_required')])]),
            'hold' => $storefront->update(['held_at' => now(), 'held_by' => $userId, 'moderation_note' => $note ?? throw ValidationException::withMessages(['note' => __('shop.error_moderation_note_required')])]),
            'lift' => $storefront->update(['held_at' => null, 'held_by' => null, 'moderation_note' => null]),
            'lock' => $storefront->update(['locked_section_types' => array_values(array_intersect(array_keys(SectionTypes::TYPES), array_map('strval', $lockedTypes)))]),
            default => throw ValidationException::withMessages(['action' => __('shop.error_moderation_action')]),
        };
        app(ResolveStorefrontAction::class)->forget($vendorId);

        return $storefront->refresh();
    }
}
