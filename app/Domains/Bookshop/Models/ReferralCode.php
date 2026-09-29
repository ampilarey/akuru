<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's own share code for the Bookstore (STATUS §5ln); made the first time they look for it. */
class ReferralCode extends Model
{
    protected $fillable = ['user_id', 'code'];
}
