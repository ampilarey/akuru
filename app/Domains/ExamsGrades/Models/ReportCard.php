<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\ReportCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    protected $fillable = [
        'student_id',
        'term_id',
        'class_id',
        'template_id',
        'status',
        'document_id',
        'generated_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportCardStatus::class,
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReportCardComment::class);
    }
}
