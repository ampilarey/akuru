<?php

namespace App\Domains\People\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The guardian's second factor. `pin_hash` is never exposed — there is no
 * accessor and no route that returns it.
 */
class PickupPin extends Model
{
    protected $fillable = ['guardian_user_id', 'pin_hash'];

    protected $hidden = ['pin_hash'];
}
