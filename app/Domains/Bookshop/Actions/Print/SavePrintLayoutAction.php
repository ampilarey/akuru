<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintLayout;
use Illuminate\Validation\ValidationException;

/** NAME_TAGS_PLAN §4.5 (NT1): a layout — tags per A4 — which must fit the page; retired, never deleted. */
class SavePrintLayoutAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintLayout $layout = null): PrintLayout
    {
        $layout ??= new PrintLayout;
        $layout->fill([
            'name' => trim((string) $data['name']),
            'columns' => max(1, (int) ($data['columns'] ?? 1)),
            'rows' => max(1, (int) ($data['rows'] ?? 1)),
            'tag_w_mm' => round((float) ($data['tag_w_mm'] ?? 0), 2),
            'tag_h_mm' => round((float) ($data['tag_h_mm'] ?? 0), 2),
            'margin_top_mm' => round((float) ($data['margin_top_mm'] ?? 0), 2),
            'margin_left_mm' => round((float) ($data['margin_left_mm'] ?? 0), 2),
            'gutter_x_mm' => round((float) ($data['gutter_x_mm'] ?? 0), 2),
            'gutter_y_mm' => round((float) ($data['gutter_y_mm'] ?? 0), 2),
            'sheet_code' => trim((string) ($data['sheet_code'] ?? '')) ?: null,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? $layout->sort_order ?? 0),
        ]);
        if ($layout->tag_w_mm < 10 || $layout->tag_h_mm < 10) {
            throw ValidationException::withMessages(['tag_w_mm' => __('print.error_layout_tag_size')]);
        }
        if (! $layout->fitsA4()) {
            [$w, $h] = $layout->footprint();
            throw ValidationException::withMessages(['columns' => __('print.error_layout_fits', ['width' => $w, 'height' => $h])]);
        }
        $layout->save();

        return $layout;
    }
}
