<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

/**
 * COMMERCE_PARITY_PLAN P6a: Akuru's charges for packing and delivering a
 * shop's orders — the office's settings over the deploy's defaults, and a
 * shop's own handling fee where the office set one.
 */
final class AkuruFulfilment
{
    public const SETTINGS = [
        'handling_fee' => 'bookshop_akuru_handling_fee',
        'delivery_fee' => 'bookshop_akuru_delivery_fee_male',
        'delivery_free_over' => 'bookshop_akuru_delivery_free_over',
    ];

    public static function handlingFee(Vendor $vendor): float
    {
        return $vendor->akuru_handling_fee !== null
            ? round((float) $vendor->akuru_handling_fee, 2)
            : (float) self::settings()['handling_fee'];
    }

    public static function packs(Vendor $vendor): bool
    {
        return $vendor->fulfilment === 'akuru';
    }

    public static function delivers(Vendor $vendor): bool
    {
        return $vendor->delivery_by === 'akuru';
    }

    /**
     * @return array{handling_fee: float, delivery_fee: float, delivery_free_over: ?float}
     */
    public static function settings(): array
    {
        try {
            $stored = app(SettingsRepositoryInterface::class)->many(array_fill_keys(array_values(self::SETTINGS), null));
        } catch (\Throwable) {
            $stored = [];
        }
        $read = fn (string $key) => ($v = $stored[self::SETTINGS[$key]] ?? null) !== null && $v !== '' ? $v : config('bookshop.akuru.'.$key);
        $free = $read('delivery_free_over');

        return [
            'handling_fee' => round(max(0, (float) $read('handling_fee')), 2),
            'delivery_fee' => round(max(0, (float) $read('delivery_fee')), 2),
            'delivery_free_over' => $free === null || $free === '' ? null : round((float) $free, 2),
        ];
    }
}
