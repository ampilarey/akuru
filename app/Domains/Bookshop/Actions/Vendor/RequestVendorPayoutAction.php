<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Money\MatureVendorEarningsAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Enums\PayoutStatus;
use App\Domains\Bookshop\Models\VendorBankDetail;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorPayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner asks for the shop's matured balance (BOOKSHOP_PLAN §5
 * "payout requests"): every earning past its return window, less what
 * earlier payouts already paid on it — so a refund after a payout comes
 * off this one. Needs bank details, the minimum, and no payout already
 * waiting. The earnings are tied to the request, so it cannot be made
 * twice; the office is told.
 */
class RequestVendorPayoutAction
{
    public function execute(VendorScope $scope): VendorPayout
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        if (! config('bookshop.money.payouts_enabled')) {
            throw ValidationException::withMessages(['payout' => __('shop.error_payouts_closed')]);
        }
        $bank = VendorBankDetail::query()->where('vendor_id', $scope->vendorId)->first();
        if ($bank === null) {
            throw ValidationException::withMessages(['payout' => __('shop.error_bank_details_first')]);
        }

        $payout = DB::transaction(function () use ($scope, $bank) {
            if (VendorPayout::query()->where('vendor_id', $scope->vendorId)->where('status', PayoutStatus::Requested->value)->exists()) {
                throw ValidationException::withMessages(['payout' => __('shop.error_payout_waiting')]);
            }
            app(MatureVendorEarningsAction::class)->execute($scope->vendorId);

            $earnings = self::matured(VendorEarning::query()->where('vendor_id', $scope->vendorId))->whereNull('open_payout_id')->lockForUpdate()->get()
                ->filter(fn (VendorEarning $e) => abs($e->balance()) >= 0.01);
            $amount = round((float) $earnings->sum(fn (VendorEarning $e) => $e->balance()), 2);
            $minimum = (float) config('bookshop.money.min_payout', 100);
            if ($amount < $minimum) {
                throw ValidationException::withMessages(['payout' => __('shop.error_below_minimum', ['amount' => number_format(max($amount, 0), 2), 'minimum' => number_format($minimum, 2)])]);
            }

            $payout = VendorPayout::query()->create([
                'vendor_id' => $scope->vendorId,
                'amount' => $amount,
                'currency' => config('bookshop.currency', 'MVR'),
                'status' => PayoutStatus::Requested->value,
                'requested_by' => $scope->userId,
                'requested_at' => now(),
                'bank_snapshot' => ['bank_name' => $bank->bank_name, 'account_name' => $bank->account_name, 'account_number' => $bank->account_number],
            ]);
            VendorEarning::query()->whereIn('id', $earnings->pluck('id'))->update(['open_payout_id' => $payout->id]);

            return $payout;
        });

        app(NotifyBookshopUserAction::class)->office(__('shop.notice_payout_requested_title'), __('shop.notice_payout_requested_body', ['vendor' => $scope->vendorName, 'amount' => $payout->currency.' '.number_format((float) $payout->amount, 2)]), '/admin/bookshop');

        return $payout;
    }

    /** Earnings whose balance may be paid: available, paid before, or reversed after a payout paid on them. */
    private static function matured(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereIn('status', [EarningStatus::Available->value, EarningStatus::Paid->value])
            ->orWhere(fn ($r) => $r->where('status', EarningStatus::Reversed->value)->where('paid_amount', '>', 0)));
    }
}
