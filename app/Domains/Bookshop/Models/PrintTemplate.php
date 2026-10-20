<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NAME_TAGS_PLAN §4.7: a design the office offers. Retired, never deleted;
 * a job keeps its own copy of the design, so an edit here never changes an
 * approved job.
 */
class PrintTemplate extends Model
{
    public const KINDS = ['name_tag', 'subject_tag', 'both'];

    public const STATUSES = ['draft', 'active', 'retired'];

    protected $fillable = ['title', 'title_dv', 'title_ar', 'kind', 'print_kind', 'print_layout_id', 'design', 'status', 'created_by', 'uses_count', 'sort_order'];

    protected function casts(): array
    {
        return ['design' => 'array', 'uses_count' => 'integer'];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(PrintLayout::class, 'print_layout_id');
    }

    public function titleIn(string $locale): string
    {
        $title = $locale === 'dv' ? $this->title_dv : ($locale === 'ar' ? $this->title_ar : null);

        return $title !== null && $title !== '' ? $title : (string) $this->title;
    }
}
