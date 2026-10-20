<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP3 (STATUS §5tk): cards.
 *
 *  - `print_layouts.bleed_mm`: how far a tag's artwork runs past its cut on
 *    every side (0 for name tags, as before; 3 for cards). The renderer
 *    draws the tag that much larger and puts the crop marks at the sheet's
 *    edges.
 *  - Four card layouts, with a 3 mm bleed and 6 mm gutters: business cards
 *    8 to an A4 and 18 to an A3, A6 place and gift cards 2 to an A4, A5
 *    invitations 1 to an A4. (The plan's "A6 4 per A4, A5 2 per A4" leave no
 *    room for a bleed between cards.)
 *  - Five card designs, made of text and shapes only, as `active`.
 *
 * Catalogues, not events: no academic year (rule 10). Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_layouts', function (Blueprint $table) {
            $table->decimal('bleed_mm', 4, 1)->default(0)->after('gutter_y_mm');
        });

        if (DB::table('print_layouts')->where('print_kind', 'cards')->exists()) {
            return;
        }
        $now = now();
        $layout = fn (string $name, string $size, int $cols, int $rows, float $w, float $h, float $top, float $left, int $order) => DB::table('print_layouts')->insertGetId([
            'name' => $name, 'print_kind' => 'cards', 'sheet_size' => $size, 'columns' => $cols, 'rows' => $rows, 'tag_w_mm' => $w, 'tag_h_mm' => $h,
            'margin_top_mm' => $top, 'margin_left_mm' => $left, 'gutter_x_mm' => 6, 'gutter_y_mm' => 6, 'bleed_mm' => 3, 'sheet_code' => null,
            'is_active' => true, 'sort_order' => 100 + $order, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $business = $layout('Business cards — 8 per A4', 'a4', 2, 4, 85, 55, 29.5, 17, 1);
        $layout('Business cards — 18 per A3', 'a3', 3, 6, 85, 55, 30, 15, 2);
        $a6 = $layout('Place and gift cards (A6) — 2 per A4', 'a4', 1, 2, 148, 105, 40.5, 31, 3);
        $a5 = $layout('Invitations (A5) — 1 per A4', 'a4', 1, 1, 148, 210, 43.5, 31, 4);

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
        $left = ['align' => 'left'];
        $designs = [
            ['Business — side band', 'ވިޔަފާރީ — ފަރާތު ބޭންޑް', 'عمل — شريط جانبي', $business, [85, 55], ['name', 'role', 'phone', 'email'], [
                $rect('band', 0, 0, 22, 55, '{primary}'),
                $text('name', 27, 11, 54, 10, '{name}', 'noto-sans-thaana', 14, '{primary}', ['field' => 'name'] + $left),
                $text('role', 27, 22, 54, 7, '{role}', 'figtree', 9, '#2B1B1E', ['field' => 'role', 'weight' => 400] + $left),
                $text('phone', 27, 35, 54, 6, '{phone}', 'figtree', 8, '#2B1B1E', ['field' => 'phone', 'weight' => 400] + $left),
                $text('email', 27, 42, 54, 6, '{email}', 'figtree', 8, '#2B1B1E', ['field' => 'email', 'weight' => 400] + $left),
            ]],
            ['Business — top band', 'ވިޔަފާރީ — މަތީ ބޭންޑް', 'عمل — شريط علوي', $business, [85, 55], ['name', 'role', 'phone', 'email'], [
                $rect('band', 0, 0, 85, 16, '{primary}'),
                $text('name', 5, 20, 75, 11, '{name}', 'noto-sans-thaana', 15, '{primary}', ['field' => 'name']),
                $text('role', 5, 31, 75, 7, '{role}', 'figtree', 9, '#2B1B1E', ['field' => 'role', 'weight' => 400]),
                $text('phone', 5, 41, 75, 5, '{phone}', 'figtree', 8, '#2B1B1E', ['field' => 'phone', 'weight' => 400]),
                $text('email', 5, 47, 75, 5, '{email}', 'figtree', 8, '#2B1B1E', ['field' => 'email', 'weight' => 400]),
            ]],
            ['Business — centred', 'ވިޔަފާރީ — މެދުގައި', 'عمل — في الوسط', $business, [85, 55], ['name', 'role', 'phone', 'email'], [
                $text('name', 5, 10, 75, 12, '{name}', 'lora', 16, '{primary}', ['field' => 'name']),
                $text('role', 5, 23, 75, 7, '{role}', 'figtree', 9, '#2B1B1E', ['field' => 'role', 'weight' => 400]),
                $rect('rule', 27.5, 32, 30, 0.6, '{primary}'),
                $text('phone', 5, 36, 75, 6, '{phone}', 'figtree', 8, '#2B1B1E', ['field' => 'phone', 'weight' => 400]),
                $text('email', 5, 43, 75, 6, '{email}', 'figtree', 8, '#2B1B1E', ['field' => 'email', 'weight' => 400]),
                $rect('foot', 0, 52, 85, 3, '{secondary}'),
            ]],
            ['Place card', 'ނަން ކާޑު', 'بطاقة مكان', $a6, [148, 105], ['name', 'role'], [
                $rect('top', 0, 0, 148, 14, '{secondary}'),
                $text('name', 10, 32, 128, 30, '{name}', 'noto-sans-thaana', 32, '{primary}', ['field' => 'name']),
                $text('role', 10, 64, 128, 12, '{role}', 'figtree', 14, '#2B1B1E', ['field' => 'role', 'weight' => 400]),
                $rect('bottom', 0, 91, 148, 14, '{secondary}'),
            ]],
            ['Invitation', 'ދަޢުވަތު ކާޑު', 'دعوة', $a5, [148, 210], ['name', 'school', 'year'], [
                $rect('ground', 0, 0, 148, 210, '{secondary}'),
                $rect('card', 10, 10, 128, 190, '#FFFFFF', ['stroke' => '{primary}', 'stroke_mm' => 0.8, 'radius' => 3]),
                $text('name', 16, 60, 116, 34, '{name}', 'noto-sans-thaana', 30, '{primary}', ['field' => 'name']),
                $text('school', 16, 104, 116, 16, '{school}', 'lora', 14, '#2B1B1E', ['field' => 'school', 'weight' => 400]),
                $text('year', 16, 132, 116, 12, '{year}', 'figtree', 12, '{primary}', ['field' => 'year', 'weight' => 400]),
            ]],
        ];
        foreach ($designs as $i => [$en, $dv, $ar, $layoutId, [$w, $h], $keys, $elements]) {
            DB::table('print_templates')->insert([
                'title' => $en, 'title_dv' => $dv, 'title_ar' => $ar, 'kind' => 'name_tag', 'print_kind' => 'cards', 'print_layout_id' => $layoutId,
                'design' => json_encode(['v' => 1, 'tag' => ['w' => $w, 'h' => $h], 'background' => ['color' => '#FFFFFF', 'image' => null], 'elements' => $elements, 'palette' => $palette, 'fields' => $fields($keys)], JSON_UNESCAPED_UNICODE),
                'status' => 'active', 'sort_order' => 100 + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('print_templates')->where('print_kind', 'cards')->whereNull('created_by')->delete();
        DB::table('print_layouts')->where('print_kind', 'cards')->whereNotIn('id', DB::table('print_jobs')->select('print_layout_id'))->delete();
        Schema::table('print_layouts', fn (Blueprint $table) => $table->dropColumn('bleed_mm'));
    }
};
