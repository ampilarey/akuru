<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN §4.2: a paper the desk prints on, with its price per sheet in black and white and in colour — stock of one sheet size (PP1). */
class PrintPaperOption extends Model
{
    public const KINDS = ['plain', 'card', 'glossy', 'sticker', 'other'];

    protected $fillable = ['name', 'name_dv', 'name_ar', 'gsm', 'kind', 'sheet_size', 'price_bw', 'price_colour', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['gsm' => 'integer', 'price_bw' => 'decimal:2', 'price_colour' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** The active papers of a sheet size with a price, in the desk's order. */
    public static function pricedFor(?string $sheetSize)
    {
        return self::query()->where('is_active', true)->where('sheet_size', $sheetSize ?? 'a4')
            ->where(fn ($q) => $q->where('price_bw', '>', 0)->orWhere('price_colour', '>', 0))
            ->orderBy('sort_order')->orderBy('id');
    }

    /** The paper a new job of this sheet size starts on: the first priced in colour, else the first priced at all. */
    public static function firstFor(?string $sheetSize): ?self
    {
        return self::pricedFor($sheetSize)->where('price_colour', '>', 0)->first() ?? self::pricedFor($sheetSize)->first();
    }

    public function nameIn(string $locale): string
    {
        $name = $locale === 'dv' ? $this->name_dv : ($locale === 'ar' ? $this->name_ar : null);

        return $name !== null && $name !== '' ? $name : (string) $this->name;
    }
}
