<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Support\PrintSheet;
use Illuminate\Database\Eloquent\Model;

/** NAME_TAGS_PLAN §4.5: tags per sheet — a grid in millimetres, on an A4, A3 or A5 sheet (PP1). */
class PrintLayout extends Model
{
    /** A4, the size every layout had before PP1 and the default. */
    public const PAGE_W = 210.0;

    public const PAGE_H = 297.0;

    /** PP4: a tag's outline — a rectangle, or a circle (round stickers). */
    public const SHAPES = ['rect', 'circle'];

    protected $fillable = ['name', 'print_kind', 'sheet_size', 'shape', 'columns', 'rows', 'tag_w_mm', 'tag_h_mm', 'margin_top_mm', 'margin_left_mm', 'gutter_x_mm', 'gutter_y_mm', 'bleed_mm', 'sheet_code', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'columns' => 'integer', 'rows' => 'integer',
            'tag_w_mm' => 'float', 'tag_h_mm' => 'float', 'margin_top_mm' => 'float', 'margin_left_mm' => 'float', 'gutter_x_mm' => 'float', 'gutter_y_mm' => 'float', 'bleed_mm' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function tagsPerSheet(): int
    {
        return (int) $this->columns * (int) $this->rows;
    }

    /** The width and height the grid takes on the page, margins included. */
    public function footprint(): array
    {
        return [
            round($this->margin_left_mm + $this->columns * $this->tag_w_mm + max(0, $this->columns - 1) * $this->gutter_x_mm, 2),
            round($this->margin_top_mm + $this->rows * $this->tag_h_mm + max(0, $this->rows - 1) * $this->gutter_y_mm, 2),
        ];
    }

    /** The sheet's width and height in mm. @return array{0: float, 1: float} */
    public function sheetMm(): array
    {
        return PrintSheet::mm($this->sheet_size);
    }

    public function fitsSheet(): bool
    {
        [$w, $h] = $this->footprint();
        [$pw, $ph] = $this->sheetMm();
        $bleed = (float) $this->bleed_mm;

        return $w + $bleed <= $pw + 0.01 && $h + $bleed <= $ph + 0.01;
    }

    /** PP3: room for the bleed — the margins at least the bleed, and the gutters twice it, so no tag's bleed runs onto another. */
    public function roomForBleed(): bool
    {
        $bleed = (float) $this->bleed_mm;
        if ($bleed <= 0) {
            return true;
        }

        return $this->margin_left_mm + 0.01 >= $bleed && $this->margin_top_mm + 0.01 >= $bleed
            && ($this->columns < 2 || $this->gutter_x_mm + 0.01 >= 2 * $bleed)
            && ($this->rows < 2 || $this->gutter_y_mm + 0.01 >= 2 * $bleed);
    }
}
