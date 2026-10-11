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

    /**
     * In the page's language (slice PY1): the receipt names the method, and
     * so does the office's one-enrolment page when it records money by hand.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => __('payments.method_cash'),
            self::BankTransfer => __('payments.method_bank_transfer'),
            self::Cheque => __('payments.method_cheque'),
            self::Card => __('payments.method_card'),
            self::Wallet => __('payments.method_wallet'),
            self::GiftCard => __('payments.method_gift_card'),
            self::Other => __('payments.method_other'),
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
