<?php

namespace App\Domains\Commerce\Listeners;

use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Commerce\Models\GiftCardTransaction;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\GiftCardCodeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * §15.3's last two arrows: "→ webhook → generate code". The only path from
 * a buyer's money to a gift card (§43.5), and the only moment the plain
 * code exists outside `IssueGiftCardAction`'s return value (§43.19): it is
 * sent to the recipient and never written down. The order records where it
 * went, masked, and when.
 *
 * Idempotent: a webhook that arrives twice issues once, because the order
 * moves pending → paid inside a lock and only a pending order issues.
 */
class IssueGiftCardOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'gift_card_order' || $payment->payable_id === null) {
            return;
        }

        $issued = DB::transaction(function () use ($payment) {
            $order = GiftCardOrder::query()->whereKey($payment->payable_id)->lockForUpdate()->first();
            if ($order === null || $order->status !== 'pending') {
                return null;
            }

            // B4b (§18): the card carries the campaign's bonus on top of what
            // was paid; the bonus is a row on the card's own ledger, so the
            // liability report and the office can see where it came from.
            $bonus = round((float) $order->bonus_amount, 2);
            $result = app(IssueGiftCardAction::class)->execute([
                'amount' => (float) $order->amount + $bonus,
                'currency' => $order->currency,
                'purchaser_user_id' => $order->user_id,
                'recipient_name' => $order->recipient_name,
                'recipient_email' => $order->recipient_email,
                'recipient_mobile' => $order->recipient_mobile,
                'message' => $order->message,
                'created_by' => $order->user_id,
                // B10 (§15.2): the office's expiry rule for bought cards —
                // months from purchase, or none while the knob is zero.
                'expires_at' => ($months = app(\App\Domains\Library\Actions\ResolveLibrarySettingAction::class)->execute('gift_card_expiry_months')) > 0
                    ? now()->addMonths($months)
                    : null,
            ]);

            if ($bonus > 0) {
                GiftCardTransaction::query()->create([
                    'gift_card_id' => $result['gift_card']->id,
                    'user_id' => $order->user_id,
                    'type' => 'bonus',
                    'amount' => $bonus,
                    'note' => 'Campaign bonus'.($order->promotion_campaign_id ? ' (campaign #'.$order->promotion_campaign_id.')' : ''),
                ]);
                app(RecordDiscountRedemptionAction::class)->transition('gift_card_order', $order->id, 'confirmed');
            }

            $order->status = 'paid';
            $order->paid_at = now();
            $order->gift_card_id = $result['gift_card']->id;
            $order->save();

            return ['order' => $order, 'plain_code' => $result['plain_code']];
        });

        if ($issued === null) {
            return;
        }

        $this->deliver($issued['order'], $issued['plain_code']);
    }

    private function deliver(GiftCardOrder $order, string $plainCode): void
    {
        $via = [];
        $to = [];

        // The card's worth, bonus included — what the recipient can spend.
        $worth = number_format((float) $order->amount + (float) $order->bonus_amount, 2, '.', '');

        if ($order->recipient_email !== null) {
            Mail::to($order->recipient_email)->queue(new GiftCardCodeMail(
                recipientName: $order->recipient_name,
                amount: $worth,
                currency: $order->currency,
                plainCode: $plainCode,
                message: $order->message,
            ));
            $via[] = 'email';
            $to[] = self::maskEmail($order->recipient_email);
        }

        if ($order->recipient_mobile !== null) {
            try {
                app(SmsSenderInterface::class)->sendSms(
                    $order->recipient_mobile,
                    sprintf(
                        'Akuru Institute gift card for %s: %s %s. Code %s. Redeem at %s',
                        $order->recipient_name,
                        $order->currency,
                        $worth,
                        $plainCode,
                        rtrim((string) config('app.url'), '/').'/my-wallet',
                    ),
                );
                $via[] = 'sms';
                $to[] = self::maskMobile($order->recipient_mobile);
            } catch (\Throwable $e) {
                // The email, if any, still carries the code. Log rather than
                // fail the webhook: the payment is confirmed either way.
                Log::warning('Gift card SMS failed.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        $order->delivered_via = $via === [] ? null : implode('+', $via);
        $order->delivered_to = $to === [] ? null : implode(', ', $to);
        $order->delivered_at = $via === [] ? null : now();
        $order->save();
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public static function maskMobile(string $mobile): string
    {
        return str_repeat('*', max(0, strlen($mobile) - 3)).substr($mobile, -3);
    }
}
