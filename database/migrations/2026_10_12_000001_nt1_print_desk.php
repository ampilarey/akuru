<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NAME_TAGS_PLAN §8 and §11 NT1 (STATUS §5ro): the print desk's own lists —
 * the papers it prints on and their two prices, the finishing it offers, the
 * layouts (tags per A4), the subject sets a grade's tags come in — and the
 * Akuru vendor the print products belong to.
 *
 *  - The four tables are catalogues, not events: no academic year (rule 10).
 *  - The Akuru vendor (`vendors.kind = 'akuru'`, the column is U1's), the
 *    *Print* category and the draft product *Name tags — A4* are not
 *    seeded here: `PrintDesk::ensure()` makes them the first time the
 *    office opens the print desk, so a Bookstore with no print desk has
 *    no Akuru shop in its lists.
 *  - Seeds carry names, not prices (decision D2): every paper and finishing
 *    starts inactive at 0.00 and the office prices and activates them.
 *
 * All additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedTinyInteger('columns');
            $table->unsignedTinyInteger('rows');
            $table->decimal('tag_w_mm', 6, 2);
            $table->decimal('tag_h_mm', 6, 2);
            $table->decimal('margin_top_mm', 6, 2)->default(0);
            $table->decimal('margin_left_mm', 6, 2)->default(0);
            $table->decimal('gutter_x_mm', 6, 2)->default(0);
            $table->decimal('gutter_y_mm', 6, 2)->default(0);
            $table->string('sheet_code', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('print_paper_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('name_dv', 120)->nullable();
            $table->string('name_ar', 120)->nullable();
            $table->unsignedSmallInteger('gsm')->nullable();
            $table->string('kind', 20)->default('plain');
            $table->decimal('price_bw', 10, 2)->default(0);
            $table->decimal('price_colour', 10, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('print_finishing_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('name_dv', 120)->nullable();
            $table->string('name_ar', 120)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('cuts_sheet')->default(false);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('print_subject_sets', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('label_dv', 120)->nullable();
            $table->string('label_ar', 120)->nullable();
            $table->json('subjects');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('print_layouts')->insert([
            ['name' => 'A4 2 × 4 (8 tags)', 'columns' => 2, 'rows' => 4, 'tag_w_mm' => 95, 'tag_h_mm' => 60, 'margin_top_mm' => 21, 'margin_left_mm' => 7.5, 'gutter_x_mm' => 5, 'gutter_y_mm' => 5, 'sheet_code' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'A4 2 × 5 (10 tags)', 'columns' => 2, 'rows' => 5, 'tag_w_mm' => 95, 'tag_h_mm' => 50, 'margin_top_mm' => 13.5, 'margin_left_mm' => 7.5, 'gutter_x_mm' => 5, 'gutter_y_mm' => 5, 'sheet_code' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'A4 3 × 4 (12 tags)', 'columns' => 3, 'rows' => 4, 'tag_w_mm' => 64, 'tag_h_mm' => 60, 'margin_top_mm' => 21, 'margin_left_mm' => 4, 'gutter_x_mm' => 5, 'gutter_y_mm' => 5, 'sheet_code' => null, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'A4 3 × 7 (21 labels)', 'columns' => 3, 'rows' => 7, 'tag_w_mm' => 63.5, 'tag_h_mm' => 38.1, 'margin_top_mm' => 15.1, 'margin_left_mm' => 7.2, 'gutter_x_mm' => 2.5, 'gutter_y_mm' => 0, 'sheet_code' => 'L7160', 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'A4 3 × 8 (24 labels)', 'columns' => 3, 'rows' => 8, 'tag_w_mm' => 63.5, 'tag_h_mm' => 33.9, 'margin_top_mm' => 13.3, 'margin_left_mm' => 7.2, 'gutter_x_mm' => 2.5, 'gutter_y_mm' => 0, 'sheet_code' => 'L7159', 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('print_paper_options')->insert([
            ['name' => 'Plain paper', 'name_dv' => 'އާދައިގެ ކަރުދާސް', 'name_ar' => 'ورق عادي', 'gsm' => 80, 'kind' => 'plain', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Card', 'name_dv' => 'ކާޑު ކަރުދާސް', 'name_ar' => 'ورق مقوّى', 'gsm' => 200, 'kind' => 'card', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Glossy photo paper', 'name_dv' => 'ގްލޮސީ ފޮޓޯ ކަރުދާސް', 'name_ar' => 'ورق صور لامع', 'gsm' => 200, 'kind' => 'glossy', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sticker sheet', 'name_dv' => 'ސްޓިކަރ ކަރުދާސް', 'name_ar' => 'ورق ملصقات', 'gsm' => null, 'kind' => 'sticker', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('print_finishing_options')->insert([
            ['name' => 'Lamination', 'name_dv' => 'ލެމިނޭޓްކުރުން', 'name_ar' => 'تغليف حراري', 'cuts_sheet' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cut into tags', 'name_dv' => 'ޓެގްތަކަށް ކަފާލުން', 'name_ar' => 'قصّ إلى بطاقات', 'cuts_sheet' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rounded corners', 'name_dv' => 'ކަންތައް ވަށްކުރުން', 'name_ar' => 'زوايا مستديرة', 'cuts_sheet' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hole punch', 'name_dv' => 'ލޯވަޅު އެޅުން', 'name_ar' => 'ثقب للحبل', 'cuts_sheet' => false, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $subjects = json_encode([
            ['en' => 'Dhivehi', 'dv' => 'ދިވެހި', 'ar' => 'الديفيهية'],
            ['en' => 'English', 'dv' => 'އިނގިރޭސި', 'ar' => 'الإنجليزية'],
            ['en' => 'Mathematics', 'dv' => 'ހިސާބު', 'ar' => 'الرياضيات'],
            ['en' => 'Islam', 'dv' => 'އިސްލާމް', 'ar' => 'التربية الإسلامية'],
            ['en' => "Qur'an", 'dv' => 'ޤުރުއާން', 'ar' => 'القرآن'],
            ['en' => 'Environmental studies', 'dv' => 'ތިމާވެށި', 'ar' => 'العلوم البيئية'],
            ['en' => 'Arabic', 'dv' => 'ޢަރަބި', 'ar' => 'العربية'],
            ['en' => 'Practical arts', 'dv' => 'ޕްރެކްޓިކަލް އާޓްސް', 'ar' => 'الفنون العملية'],
        ], JSON_UNESCAPED_UNICODE);
        $grades = [[1, 'Grade 1', 'ގްރޭޑް 1', 'الصف الأول'], [2, 'Grade 2', 'ގްރޭޑް 2', 'الصف الثاني'], [3, 'Grade 3', 'ގްރޭޑް 3', 'الصف الثالث']];
        foreach ($grades as [$order, $en, $dv, $ar]) {
            DB::table('print_subject_sets')->insert(['label' => $en, 'label_dv' => $dv, 'label_ar' => $ar, 'subjects' => $subjects, 'is_active' => true, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]);
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('print_subject_sets');
        Schema::dropIfExists('print_finishing_options');
        Schema::dropIfExists('print_paper_options');
        Schema::dropIfExists('print_layouts');
    }
};
