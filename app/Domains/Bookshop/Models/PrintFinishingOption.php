<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN §4.3: a finishing the desk offers, priced per sheet; `cuts_sheet` means loose tags are handed over. */
class PrintFinishingOption extends Model
{
    protected $fillable = ['vendor_id', 'name', 'name_dv', 'name_ar', 'price', 'cuts_sheet', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cuts_sheet' => 'boolean', 'is_active' => 'boolean'];
    }

    /** PP7a: a printer's own finishings — Akuru's unowned, a shop's carrying its id. */
    public function scopeOfPrinter($query, ?int $printerId)
    {
        return $printerId === null ? $query->whereNull('vendor_id') : $query->where('vendor_id', $printerId);
    }

    public function nameIn(string $locale): string
    {
        $name = $locale === 'dv' ? $this->name_dv : ($locale === 'ar' ? $this->name_ar : null);

        return $name !== null && $name !== '' ? $name : (string) $this->name;
    }
}
