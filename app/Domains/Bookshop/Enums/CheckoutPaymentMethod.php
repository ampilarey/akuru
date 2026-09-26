<?php

namespace App\Domains\Bookshop\Enums;

/**
 * Decision 7: card (BML), wallet, and bank transfer with a slip. `none` is
 * a checkout a discount brought to zero — nothing to pay.
 */
enum CheckoutPaymentMethod: string
{
    case Card = 'card';
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
    case None = 'none';
}
