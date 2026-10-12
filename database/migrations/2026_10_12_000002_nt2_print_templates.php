<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NAME_TAGS_PLAN §4.7, §5.1 and §11 NT2 (STATUS §5rp): the designs the
 * office offers, and the office's own picture library for them.
 *
 *  - `print_templates`: a title in three languages, the kind of tag it
 *    makes, its default layout, the design (plan §5.1, millimetres), a
 *    status (draft, active, retired — retired, never deleted: a job keeps
 *    its own copy of the design, so a template's later edit never changes
 *    an approved job), who made it and how often it was used.
 *  - `print_images`: Akuru's artwork for templates — public media the
 *    designer places (a crest, a border), with an alt text.
 *
 * Five designs ship as `active`, made of text and shapes only (no artwork
 * file to licence), on the 2 × 4 layout. Catalogues, not events: no
 * academic year (rule 10). Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('title_dv', 120)->nullable();
            $table->string('title_ar', 120)->nullable();
            $table->string('kind', 20)->default('name_tag');
            $table->foreignId('print_layout_id')->nullable()->constrained('print_layouts')->nullOnDelete();
            $table->json('design');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        Schema::create('print_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('alt', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $layout = DB::table('print_layouts')->where('columns', 2)->where('rows', 4)->value('id');
        $now = now();
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
            ['Plain', 'އާދައިގެ', 'بسيط', 'name_tag', ['name', 'class'], [
                $rect('frame', 0, 0, 95, 60, '#FFFFFF', ['stroke' => '{primary}', 'stroke_mm' => 0.6, 'radius' => 3]),
                $text('name', 5, 16, 85, 18, '{name}', 'noto-sans-thaana', 24, '{primary}', ['field' => 'name']),
                $text('class', 5, 38, 85, 10, '{class}', 'figtree', 12, '#2B1B1E', ['field' => 'class', 'weight' => 400]),
            ]],
            ['Maroon band', 'ރަތް ބޭންޑް', 'شريط أحمر', 'both', ['name', 'subject'], [
                $rect('band', 0, 0, 95, 22, '{primary}'),
                $text('name', 4, 3, 87, 16, '{name}', 'noto-sans-thaana', 22, '#FFFFFF', ['field' => 'name']),
                $text('subject', 4, 28, 87, 14, '{subject}', 'poppins', 16, '{primary}', ['field' => 'subject']),
                $rect('foot', 0, 54, 95, 6, '{secondary}'),
            ]],
            ['School crest', 'ސްކޫލް ކްރެސްޓް', 'شعار المدرسة', 'name_tag', ['name', 'school', 'class'], [
                $rect('frame', 0, 0, 95, 60, '{secondary}', ['radius' => 4]),
                ['id' => 'crest', 'type' => 'ellipse', 'x' => 6, 'y' => 15, 'w' => 28, 'h' => 28, 'fill' => '#FFFFFF', 'stroke' => '{primary}', 'stroke_mm' => 0.8, 'rotate' => 0, 'locked' => true],
                $text('school', 38, 8, 52, 10, '{school}', 'lora', 10, '{primary}', ['field' => 'school', 'weight' => 400]),
                $text('name', 38, 20, 52, 18, '{name}', 'noto-sans-thaana', 20, '#2B1B1E', ['field' => 'name']),
                $text('class', 38, 41, 52, 9, '{class}', 'figtree', 11, '{primary}', ['field' => 'class', 'weight' => 400]),
            ]],
            ['Playful', 'މަޖާ', 'مرح', 'name_tag', ['name', 'class'], [
                $rect('frame', 0, 0, 95, 60, '{secondary}', ['radius' => 10]),
                $rect('card', 6, 6, 83, 48, '#FFFFFF', ['radius' => 8, 'stroke' => '{primary}', 'stroke_mm' => 1]),
                $text('name', 10, 14, 75, 20, '{name}', 'fredoka', 26, '{primary}', ['field' => 'name']),
                $text('class', 10, 38, 75, 10, '{class}', 'nunito', 12, '#2B1B1E', ['field' => 'class', 'weight' => 400]),
            ]],
            ['Subject strip', 'މާއްދާ ސްޓްރިޕް', 'شريط المادة', 'subject_tag', ['subject', 'name'], [
                $rect('frame', 0, 0, 95, 60, '#FFFFFF', ['stroke' => '{primary}', 'stroke_mm' => 0.5]),
                $text('subject', 4, 8, 87, 22, '{subject}', 'noto-sans-thaana', 26, '{primary}', ['field' => 'subject']),
                $rect('strip', 0, 42, 95, 18, '{primary}'),
                $text('name', 4, 45, 87, 12, '{name}', 'noto-sans-thaana', 14, '#FFFFFF', ['field' => 'name', 'weight' => 400]),
            ]],
        ];
        foreach ($designs as $i => [$en, $dv, $ar, $kind, $keys, $elements]) {
            DB::table('print_templates')->insert([
                'title' => $en, 'title_dv' => $dv, 'title_ar' => $ar, 'kind' => $kind, 'print_layout_id' => $layout,
                'design' => json_encode(['v' => 1, 'tag' => ['w' => 95, 'h' => 60], 'background' => ['color' => '#FFFFFF', 'image' => null], 'elements' => $elements, 'palette' => $palette, 'fields' => $fields($keys)], JSON_UNESCAPED_UNICODE),
                'status' => 'active', 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_images');
        Schema::dropIfExists('print_templates');
    }
};
