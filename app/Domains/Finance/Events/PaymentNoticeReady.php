<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\DTOs\PaymentNoticeData;

/**
 * A confirmed payment, reduced to the plain values its notices need, ready for
 * whoever sends them.
 *
 * Separate from `PaymentConfirmed` on purpose, and the difference is the whole
 * point of having two events:
 *
 *  - **`PaymentConfirmed`** carries the Eloquent `Payment` and fires *inside*
 *    the payment transaction. Domains grant access by listening to it, so a
 *    failed listener rolls back with the money. That is money→access, and it
 *    must stay exactly as strict as it is.
 *  - **`PaymentNoticeReady`** carries no model and fires *after commit*. An
 *    SMTP timeout is not a reason to un-confirm a payment, and a mail send
 *    inside a transaction holds it open for the length of a network call.
 *
 * Carrying a DTO rather than the model is also what lets Notifications listen
 * at all: rule 3 permits another domain's Events and DTOs across the boundary,
 * but not its Models — and §41 names this exact arrangement.
 */
class PaymentNoticeReady
{
    public function __construct(public PaymentNoticeData $notice) {}
}
