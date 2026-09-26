<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorBankDetail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Where the shop's payouts go (BOOKSHOP_PLAN §5 "Bank details for
 * payouts"; FITRAH.md: entered by the owner in her own portal, never
 * through the office or a kit file). Owners only; one row per shop; the
 * portal shows the number masked to its last four digits.
 */
class SaveVendorBankDetailsAction
{
    /**
     * @return array{bank_name: string, account_name: string, account_number_masked: string, currency: string, updated_at: ?string}|null
     */
    public function get(VendorScope $scope): ?array
    {
        $row = VendorBankDetail::query()->where('vendor_id', $scope->vendorId)->first();

        return $row === null ? null : [
            'bank_name' => $row->bank_name,
            'account_name' => $row->account_name,
            'account_number_masked' => $row->maskedAccountNumber(),
            'currency' => $row->currency,
            'updated_at' => $row->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(VendorScope $scope, array $data): VendorBankDetail
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $number = preg_replace('/[^0-9A-Za-z]/', '', (string) ($data['account_number'] ?? ''));
        if ($number === '' || strlen($number) < 6) {
            throw ValidationException::withMessages(['account_number' => __('shop.error_account_number')]);
        }

        return VendorBankDetail::query()->updateOrCreate(
            ['vendor_id' => $scope->vendorId],
            [
                'bank_name' => mb_substr(trim((string) $data['bank_name']), 0, 120),
                'account_name' => mb_substr(trim((string) $data['account_name']), 0, 160),
                'account_number' => $number,
                'currency' => config('bookshop.currency', 'MVR'),
                'updated_by' => $scope->userId,
            ],
        );
    }
}
