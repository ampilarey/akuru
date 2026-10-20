<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Bookshop\Support\PrintSheet;
use Illuminate\Validation\ValidationException;

/** NAME_TAGS_PLAN §4.5 (NT1): a layout — tags per sheet, on A4, A3 or A5 (PP1) — which must fit its sheet; retired, never deleted. */
class SavePrintLayoutAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintLayout $layout = null): PrintLayout
    {
        $layout ??= new PrintLayout;
        $layout->fill([
            'name' => trim((string) $data['name']),
            'print_kind' => PrintProducts::valid($data['print_kind'] ?? null) ? $data['print_kind'] : ($layout->print_kind ?? PrintProducts::DEFAULT),
            'shape' => in_array($data['shape'] ?? null, PrintLayout::SHAPES, true) ? $data['shape'] : ($layout->shape ?? 'rect'),
            'sheet_size' => in_array($data['sheet_size'] ?? null, PrintSheet::codes(), true) ? $data['sheet_size'] : ($layout->sheet_size ?? PrintSheet::DEFAULT),
            'columns' => max(1, (int) ($data['columns'] ?? 1)),
            'rows' => max(1, (int) ($data['rows'] ?? 1)),
            'tag_w_mm' => round((float) ($data['tag_w_mm'] ?? 0), 2),
            'tag_h_mm' => round((float) ($data['tag_h_mm'] ?? 0), 2),
            'margin_top_mm' => round((float) ($data['margin_top_mm'] ?? 0), 2),
            'margin_left_mm' => round((float) ($data['margin_left_mm'] ?? 0), 2),
            'gutter_x_mm' => round((float) ($data['gutter_x_mm'] ?? 0), 2),
            'gutter_y_mm' => round((float) ($data['gutter_y_mm'] ?? 0), 2),
            'bleed_mm' => min(5.0, max(0.0, round((float) ($data['bleed_mm'] ?? $layout->bleed_mm ?? 0), 1))),
            'sheet_code' => trim((string) ($data['sheet_code'] ?? '')) ?: null,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? $layout->sort_order ?? 0),
        ]);
        if ($layout->tag_w_mm < 10 || $layout->tag_h_mm < 10) {
            throw ValidationException::withMessages(['tag_w_mm' => __('print.error_layout_tag_size')]);
        }
        if (! $layout->fitsSheet()) {
            [$w, $h] = $layout->footprint();
            [$pw, $ph] = $layout->sheetMm();
            throw ValidationException::withMessages(['columns' => __('print.error_layout_fits', ['width' => $w, 'height' => $h, 'size' => PrintSheet::label($layout->sheet_size), 'page_w' => $pw, 'page_h' => $ph])]);
        }
        if (! $layout->roomForBleed()) {
            throw ValidationException::withMessages(['bleed_mm' => __('print.error_layout_bleed', ['bleed' => $layout->bleed_mm, 'gutter' => 2 * $layout->bleed_mm])]);
        }
        $layout->save();

        return $layout;
    }
}
