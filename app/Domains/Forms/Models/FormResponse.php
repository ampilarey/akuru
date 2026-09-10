<?php

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormResponse extends Model
{
    protected $fillable = [
        'form_id',
        'user_id',
        'student_id',
        'invoice_id',
        'academic_year_id',
        'answers',
        'submitted_at',
        'confirmed_at',
        'confirmed_by_user_id',
    ];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
