<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One regeneration of a published report card: the document it replaced,
 * the one it produced, who asked and why (ADR-038). Append-only.
 */
class ReportCardRevision extends Model
{
    protected $fillable = [
        'report_card_id',
        'academic_year_id',
        'term_id',
        'superseded_document_id',
        'document_id',
        'actor_id',
        'reason',
    ];

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
