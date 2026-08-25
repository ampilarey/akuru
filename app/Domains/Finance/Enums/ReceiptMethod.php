<?php

namespace App\Domains\Finance\Enums;

enum ReceiptMethod: string
{
    case Bml = 'bml';
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Wallet = 'wallet';
    case GiftCard = 'gift_card';
    case Waiver = 'waiver';
}
