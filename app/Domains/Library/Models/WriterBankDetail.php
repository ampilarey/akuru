<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class WriterBankDetail extends Model
{
    protected $fillable = [
        'writer_id',
        'bank_name',
        'account_name',
        'account_number',
        'currency',
    ];
}
