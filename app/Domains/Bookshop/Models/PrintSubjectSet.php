<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NAME_TAGS_PLAN §4.4: a grade's subjects, each in three languages, so a
 * child's subject tags come as a set. The office's own list, not a link to
 * the school's subjects (rule 3).
 */
class PrintSubjectSet extends Model
{
    protected $fillable = ['label', 'label_dv', 'label_ar', 'subjects', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['subjects' => 'array', 'is_active' => 'boolean'];
    }

    public function labelIn(string $locale): string
    {
        $label = $locale === 'dv' ? $this->label_dv : ($locale === 'ar' ? $this->label_ar : null);

        return $label !== null && $label !== '' ? $label : (string) $this->label;
    }
}
