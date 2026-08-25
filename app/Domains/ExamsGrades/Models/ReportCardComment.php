<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\ReportCardCommentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardComment extends Model
{
    protected $fillable = [
        'report_card_id',
        'comment_type',
        'comment',
        'comment_arabic',
        'comment_dhivehi',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'comment_type' => ReportCardCommentType::class,
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
