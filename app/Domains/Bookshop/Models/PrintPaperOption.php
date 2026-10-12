<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN §4.2: a paper the desk prints on, with its price per sheet in black and white and in colour. */
class PrintPaperOption extends Model
{
    public const KINDS = ['plain', 'card', 'glossy', 'sticker', 'other'];

    protected $fillable = ['name', 'name_dv', 'name_ar', 'gsm', 'kind', 'price_bw', 'price_colour', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['gsm' => 'integer', 'price_bw' => 'decimal:2', 'price_colour' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function nameIn(string $locale): string
    {
        $name = $locale === 'dv' ? $this->name_dv : ($locale === 'ar' ? $this->name_ar : null);

        return $name !== null && $name !== '' ? $name : (string) $this->name;
    }
}
