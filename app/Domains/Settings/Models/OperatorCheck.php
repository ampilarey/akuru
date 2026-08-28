<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorCheck extends Model
{
    protected $table = 'operator_checklist_checks';

    protected $fillable = [
        'item_key',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
