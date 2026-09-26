<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** Where a vendor's payouts go. One row per vendor, written by its owner in the portal only. */
class VendorBankDetail extends Model
{
    protected $fillable = ['vendor_id', 'bank_name', 'account_name', 'account_number', 'currency', 'updated_by'];

    /** The account number as the portal and the office show it: the last four digits. */
    public function maskedAccountNumber(): string
    {
        $digits = preg_replace('/\s+/', '', (string) $this->account_number);

        return str_repeat('•', max(0, mb_strlen($digits) - 4)).mb_substr($digits, -4);
    }
}
