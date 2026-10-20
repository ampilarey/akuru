<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP4 (STATUS §5tl): stickers on sheets.
 *
 *  - `print_layouts.shape`: `rect` (every layout before) or `circle` — a
 *    round sticker, printed and proofed as its circle.
 *  - Four sticker layouts with a 2 mm bleed and 4 mm gutters: round 40 mm,
 *    24 to an A4 and 54 to an A3; round 60 mm, 12 to an A4; rectangle labels
 *    63 × 38 mm, 21 to an A4.
 *  - *Kiss-cut*: a finishing that cuts the stickers but not the backing, so
 *    the sheet stays whole (`cuts_sheet` false). Off and unpriced until the
 *    office prices it, like every finishing.
 *  - An A3 sticker sheet, off and unpriced.
 *  - Three sticker designs, text and shapes only, `active`.
 *
 * Rolls stay parked (PD5). Catalogues: no academic year (rule 10).
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_layouts', function (Blueprint $table) {
            $table->string('shape', 10)->default('rect')->after('print_kind');
        });

        if (DB::table('print_layouts')->where('print_kind', 'stickers')->exists()) {
            return;
        }
        $now = now();
        $layout = fn (string $name, string $shape, string $size, int $cols, int $rows, float $w, float $h, float $top, float $left, int $order) => DB::table('print_layouts')->insertGetId([
            'name' => $name, 'print_kind' => 'stickers', 'shape' => $shape, 'sheet_size' => $size, 'columns' => $cols, 'rows' => $rows, 'tag_w_mm' => $w, 'tag_h_mm' => $h,
            'margin_top_mm' => $top, 'margin_left_mm' => $left, 'gutter_x_mm' => 4, 'gutter_y_mm' => 4, 'bleed_mm' => 2, 'sheet_code' => null,
            'is_active' => true, 'sort_order' => 200 + $order, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $round40 = $layout('Round stickers 40 mm — 24 per A4', 'circle', 'a4', 4, 6, 40, 40, 18.5, 19, 1);
        $layout('Round stickers 40 mm — 54 per A3', 'circle', 'a3', 6, 9, 40, 40, 14, 18.5, 2);
        $round60 = $layout('Round stickers 60 mm — 12 per A4', 'circle', 'a4', 3, 4, 60, 60, 22.5, 11, 3);
        $labels = $layout('Labels 63 × 38 mm — 21 per A4', 'rect', 'a4', 3, 7, 63, 38, 3.5, 6.5, 4);

        DB::table('print_finishing_options')->insert([
            'name' => 'Kiss-cut (peel off the sheet)', 'name_dv' => 'ކިސް ކަޓް (ޝީޓުން ބާލެވޭ ގޮތަށް)', 'name_ar' => 'قصّ سطحي (تُنزع من الورقة)',
            'price' => 0, 'cuts_sheet' => false, 'is_active' => false, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('print_paper_options')->insert([
            'name' => 'Sticker sheet A3', 'name_dv' => 'ސްޓިކަރ ކަރުދާސް A3', 'name_ar' => 'ورق ملصقات A3', 'gsm' => null, 'kind' => 'sticker', 'sheet_size' => 'a3',
            'price_bw' => 0, 'price_colour' => 0, 'is_active' => false, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $text = fn (string $id, float $x, float $y, float $w, float $h, string $text, string $font, int $size, string $color, array $more = []) => $more + [
            'id' => $id, 'type' => 'text', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'text' => $text, 'font' => $font, 'size' => $size,
            'weight' => 700, 'color' => $color, 'align' => 'center', 'dir' => 'auto', 'rotate' => 0, 'locked' => false,
        ];
        $rect = fn (string $id, float $x, float $y, float $w, float $h, string $fill, array $more = []) => $more + [
            'id' => $id, 'type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'fill' => $fill, 'stroke' => null, 'stroke_mm' => 0.3, 'radius' => 0, 'rotate' => 0, 'locked' => true,
        ];
        $palette = ['scheme' => 'maroon', 'schemes' => [
            'maroon' => ['primary' => '#7A1F2B', 'secondary' => '#E9D8B4'],
            'ocean' => ['primary' => '#0F4C81', 'secondary' => '#CFE8F3'],
            'forest' => ['primary' => '#1F5F3F', 'secondary' => '#DDEBDD'],
        ]];
        $fields = fn (array $keys) => array_map(fn (string $k) => ['key' => $k, 'required' => $k === 'name'], $keys);
        $designs = [
            ['Round name sticker', 'ވަށް ނަން ސްޓިކަރު', 'ملصق اسم دائري', $round40, [40, 40], ['name', 'class'], [
                $rect('ground', 0, 0, 40, 40, '{secondary}'),
                $text('name', 3, 12, 34, 10, '{name}', 'noto-sans-thaana', 11, '{primary}', ['field' => 'name']),
                $text('class', 4, 23, 32, 6, '{class}', 'figtree', 7, '#2B1B1E', ['field' => 'class', 'weight' => 400]),
            ]],
            ['Big round sticker', 'ބޮޑު ވަށް ސްޓިކަރު', 'ملصق دائري كبير', $round60, [60, 60], ['name', 'class'], [
                $rect('ground', 0, 0, 60, 60, '{primary}'),
                ['id' => 'ring', 'type' => 'ellipse', 'x' => 4, 'y' => 4, 'w' => 52, 'h' => 52, 'fill' => null, 'stroke' => '{secondary}', 'stroke_mm' => 0.8, 'rotate' => 0, 'locked' => true],
                $text('name', 7, 20, 46, 14, '{name}', 'noto-sans-thaana', 16, '#FFFFFF', ['field' => 'name']),
                $text('class', 9, 36, 42, 7, '{class}', 'figtree', 9, '{secondary}', ['field' => 'class', 'weight' => 400]),
            ]],
            ['Book label', 'ފޮތުގެ ލޭބަލް', 'ملصق كتاب', $labels, [63, 38], ['name', 'class', 'school'], [
                $rect('band', 0, 0, 63, 10, '{primary}'),
                $text('school', 3, 1.5, 57, 7, '{school}', 'figtree', 8, '#FFFFFF', ['field' => 'school', 'weight' => 400]),
                $text('name', 3, 13, 57, 11, '{name}', 'noto-sans-thaana', 13, '{primary}', ['field' => 'name']),
                $text('class', 3, 26, 57, 7, '{class}', 'figtree', 9, '#2B1B1E', ['field' => 'class', 'weight' => 400]),
            ]],
        ];
        foreach ($designs as $i => [$en, $dv, $ar, $layoutId, [$w, $h], $keys, $elements]) {
            DB::table('print_templates')->insert([
                'title' => $en, 'title_dv' => $dv, 'title_ar' => $ar, 'kind' => 'name_tag', 'print_kind' => 'stickers', 'print_layout_id' => $layoutId,
                'design' => json_encode(['v' => 1, 'tag' => ['w' => $w, 'h' => $h], 'background' => ['color' => '#FFFFFF', 'image' => null], 'elements' => $elements, 'palette' => $palette, 'fields' => $fields($keys)], JSON_UNESCAPED_UNICODE),
                'status' => 'active', 'sort_order' => 200 + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('print_templates')->where('print_kind', 'stickers')->whereNull('created_by')->delete();
        DB::table('print_layouts')->where('print_kind', 'stickers')->whereNotIn('id', DB::table('print_jobs')->select('print_layout_id'))->delete();
        DB::table('print_finishing_options')->where('name', 'Kiss-cut (peel off the sheet)')->delete();
        DB::table('print_paper_options')->where('name', 'Sticker sheet A3')->whereNotIn('id', DB::table('print_jobs')->whereNotNull('print_paper_option_id')->select('print_paper_option_id'))->delete();
        Schema::table('print_layouts', fn (Blueprint $table) => $table->dropColumn('shape'));
    }
};
