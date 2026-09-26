<?php

namespace App\Domains\Bookshop\Enums;

/**
 * Decision 6: collect from the vendor or from Akuru; delivery in the
 * greater Malé area; the atolls by courier, or by boat with the fee paid
 * to the carrier on arrival.
 */
enum DeliveryKind: string
{
    case CollectVendor = 'collect_vendor';
    case CollectAkuru = 'collect_akuru';
    case CourierMale = 'courier_male';
    case CourierAtolls = 'courier_atolls';
    case Boat = 'boat';
}
