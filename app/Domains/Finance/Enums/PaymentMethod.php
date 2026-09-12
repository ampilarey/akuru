<?php

namespace App\Domains\Finance\Enums;

/**
 * SPEC §38 lists **"Payment method"** and **"Gateway"** as two separate fields
 * on the payments table, and CLAUDE.md rule 12 treats the distinction as load
 * bearing: "Gift cards = payment method; discounts = price reduction."
 *
 * The table only had the gateway (`provider`: bml | manual). How the money
 * actually arrived was recorded — when it was recorded at all — as English
 * prose in `notes`, seeded by a form placeholder reading
 * "Note (e.g. cash at office)". So "how much cash came through the office
 * this term" was a question the finance data could not answer, only a human
 * reading free text could.
 *
 * `Card` is deliberately **not** assigned to gateway payments. BML Connect
 * does not report an instrument back to us, and stamping every gateway
 * payment "card" would be recording a guess as a fact. A null method on a
 * `bml` payment means exactly that: whatever the gateway processed. The
 * case exists for the day a gateway does tell us, or an admin records a card
 * terminal payment taken at the office.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';

    /** Internal money, already held by the payer (Commerce wallet). */
    case Wallet = 'wallet';

    /** Rule 12: a gift card is a payment method, not a discount. */
    case GiftCard = 'gift_card';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::Cheque => 'Cheque',
            self::Card => 'Card',
            self::Wallet => 'Wallet',
            self::GiftCard => 'Gift card',
            self::Other => 'Other',
        };
    }

    /**
     * The methods an admin can assert for money received outside the gateway.
     *
     * Wallet and gift card are absent on purpose: those are spent inside the
     * product, by the payer, and an admin asserting one by hand would put a
     * second record of money that the Commerce ledger already owns (rule 11).
     *
     * @return list<self>
     */
    public static function manualCases(): array
    {
        return [self::Cash, self::BankTransfer, self::Cheque, self::Card, self::Other];
    }
}
