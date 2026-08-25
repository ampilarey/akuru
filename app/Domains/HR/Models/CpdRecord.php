<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;

class CpdRecord extends Model
{
    protected $fillable = [
        'staff_profile_id',
        'title',
        'provider',
        'hours',
        'date',
        'certificate_document_id',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:1',
            'date' => 'date',
        ];
    }
}
