<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\CheckoutStatus;
use App\Domains\Bookshop\Enums\SlipStatus;
use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The customer's proof of a bank transfer (decision 7): stored as private
 * media on their own pending bank-transfer checkout, which is then held
 * (not expired) until the office decides. The office is told.
 */
class UploadBankTransferSlipAction
{
    public function execute(int $userId, string $checkoutNumber, UploadedFile $file, ?string $reference, ?string $note): BankTransferSlip
    {
        $checkout = BookshopCheckout::query()->where('user_id', $userId)->where('number', $checkoutNumber)->firstOrFail();
        if ($checkout->payment_method !== CheckoutPaymentMethod::BankTransfer || $checkout->status !== CheckoutStatus::PendingPayment) {
            throw ValidationException::withMessages(['slip' => __('shop.error_slip_not_expected')]);
        }

        $stored = app(StorePrivateMediaAction::class)->execute(
            $file,
            $userId,
            (array) config('bookshop.bank_transfer.slip_mimes'),
            (int) config('bookshop.bank_transfer.slip_max_kilobytes', 8192) * 1024,
        );

        $slip = BankTransferSlip::query()->create([
            'bookshop_checkout_id' => $checkout->id,
            'media_file_id' => $stored['id'],
            'reference' => trim((string) $reference) ?: null,
            'note' => trim((string) $note) ?: null,
            'status' => SlipStatus::Waiting->value,
        ]);

        // The goods stay held while the office looks.
        $until = now()->addMinutes((int) config('bookshop.checkout.reservation_minutes', 30));
        $checkout->update(['expires_at' => $until]);
        $checkout->reservations()->update(['expires_at' => $until]);

        app(NotifyBookshopUserAction::class)->office(
            __('shop.notice_slip_title'),
            __('shop.notice_slip_body', ['number' => $checkout->number]),
            '/admin/bookshop',
        );

        return $slip;
    }
}
