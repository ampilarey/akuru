<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationOverride extends Model
{
    protected $fillable = [
        'locale',
        'group',
        'key',
        'value',
        'updated_by',
    ];
}
