<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRINT_PRODUCTS_PLAN PP5 (STATUS §5tm): photo prints. Data only.
 *
 *  - Five photo sizes, each a layout of the photos product, imposed on the
 *    fewest sheets with room for the printer's own margin and cut by the
 *    crop marks: 4 × 6 in landscape 2 to an A4 and portrait 4 to an A3,
 *    5 × 7 in 1 to an A4, A5 1 to an A4, A4 1 to an A3. (An A3 print needs
 *    a borderless printer or a bigger sheet: not offered.)
 *  - An A3 glossy photo paper, off and unpriced.
 *
 * No designs: a photo print is the customer's own picture (an upload).
 * Catalogues: no academic year (rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('print_layouts')->where('print_kind', 'photos')->exists()) {
            return;
        }
        $now = now();
        $sizes = [
            ['4 × 6 in landscape — 2 per A4', 'a4', 1, 2, 152.4, 101.6, 44.9, 28.8],
            ['4 × 6 in portrait — 4 per A3', 'a3', 2, 2, 101.6, 152.4, 55.6, 44.9],
            ['5 × 7 in — 1 per A4', 'a4', 1, 1, 127, 177.8, 59.6, 41.5],
            ['A5 — 1 per A4', 'a4', 1, 1, 148, 210, 43.5, 31],
            ['A4 — 1 per A3', 'a3', 1, 1, 210, 297, 61.5, 43.5],
        ];
        foreach ($sizes as $i => [$name, $sheet, $cols, $rows, $w, $h, $top, $left]) {
            DB::table('print_layouts')->insert([
                'name' => $name, 'print_kind' => 'photos', 'shape' => 'rect', 'sheet_size' => $sheet, 'columns' => $cols, 'rows' => $rows,
                'tag_w_mm' => $w, 'tag_h_mm' => $h, 'margin_top_mm' => $top, 'margin_left_mm' => $left, 'gutter_x_mm' => 4, 'gutter_y_mm' => 4,
                'bleed_mm' => 0, 'sheet_code' => null, 'is_active' => true, 'sort_order' => 300 + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('print_paper_options')->insert([
            'name' => 'Glossy photo paper A3', 'name_dv' => 'ގްލޮސީ ފޮޓޯ ކަރުދާސް A3', 'name_ar' => 'ورق صور لامع A3', 'gsm' => 200, 'kind' => 'glossy', 'sheet_size' => 'a3',
            'price_bw' => 0, 'price_colour' => 0, 'is_active' => false, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('print_layouts')->where('print_kind', 'photos')->whereNotIn('id', DB::table('print_jobs')->select('print_layout_id'))->delete();
        DB::table('print_paper_options')->where('name', 'Glossy photo paper A3')->whereNotIn('id', DB::table('print_jobs')->whereNotNull('print_paper_option_id')->select('print_paper_option_id'))->delete();
    }
};
