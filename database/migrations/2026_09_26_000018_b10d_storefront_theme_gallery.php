<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B10d: the theme gallery — the "theme marketplace" the owner asked for
 * (2026-09-26; ADR-039). Additive only (rule 9).
 *
 * A theme is a whole look: the B4 theme data (colours, fonts, scale, shape,
 * dark variant) and, optionally, CSS already cleaned by
 * `Support/CustomCss`. The office publishes them — its own, or a look a
 * shop offered — and any shop applies one to its draft. Free: paid themes
 * would be money between shops, a decision the owner has not made. Four
 * starter looks are published here so the gallery is never empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->json('theme');
            $table->text('custom_css')->nullable();
            $table->string('status', 12)->default('submitted');
            $table->string('source', 8)->default('office');
            $table->foreignId('source_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        $shape = fn (string $radius, string $button, string $card, string $banner = 'regular', string $ratio = 'square') => ['radius' => $radius, 'button' => $button, 'card' => $card, 'banner_height' => $banner, 'image_ratio' => $ratio];
        $fonts = fn (string $heading, string $body) => ['heading' => $heading, 'body' => $body, 'accent' => null, 'dhivehi' => 'Faruma', 'arabic' => 'Noto Naskh Arabic'];
        $starters = [
            ['classic-bookshop', 'Classic bookshop', 'Warm paper tones, serif headings, crisp bordered cards — a traditional bookshop.',
                ['colors' => ['primary' => '#8A5A2B', 'secondary' => '#F2E6D3', 'accent' => '#B0413E', 'page_bg' => '#FBF6EE', 'card_bg' => '#FFFFFF', 'text' => '#3A2A1A', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF'],
                    'fonts' => $fonts('Merriweather', 'Lora'), 'scale' => 'regular', 'shape' => $shape('square', 'filled', 'bordered', 'regular', 'portrait')],
                ".storefront h1, .storefront h2 { letter-spacing: .02em; }\n.storefront .sf-card, .storefront [data-product] { border-width: 2px; }"],
            ['playful-kids', 'Playful kids', 'Round shapes, a friendly rounded font and cards that lift — for toys and early readers.',
                ['colors' => ['primary' => '#0F4C81', 'secondary' => '#CFE8F3', 'accent' => '#0B7285', 'page_bg' => '#F4FAFC', 'card_bg' => '#FFFFFF', 'text' => '#12303F', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF'],
                    'fonts' => $fonts('Poppins', 'Poppins'), 'scale' => 'large', 'shape' => $shape('round', 'filled', 'shadow', 'tall', 'square')],
                ".storefront [data-product] { transition: transform .15s ease; }\n.storefront [data-product]:hover { transform: translateY(-3px); }"],
            ['modern-minimal', 'Modern minimal', 'Black, white and one blue; flat cards and a compact scale.',
                ['colors' => ['primary' => '#111827', 'secondary' => '#E5E7EB', 'accent' => '#1D4ED8', 'page_bg' => '#FFFFFF', 'card_bg' => '#F9FAFB', 'text' => '#111827', 'on_primary' => '#FFFFFF', 'on_accent' => '#FFFFFF'],
                    'fonts' => $fonts('Inter', 'Inter'), 'scale' => 'compact', 'shape' => $shape('square', 'outlined', 'flat', 'short', 'square')],
                null],
            ['evening-reading', 'Evening reading', 'A deep night palette with gold accents and soft serif text.',
                ['colors' => ['primary' => '#1E1B4B', 'secondary' => '#3B3765', 'accent' => '#F2C778', 'page_bg' => '#14122E', 'card_bg' => '#1F1C40', 'text' => '#F3F0FF', 'on_primary' => '#FFFFFF', 'on_accent' => '#1A1400'],
                    'fonts' => $fonts('Lora', 'Lora'), 'scale' => 'regular', 'shape' => $shape('soft', 'filled', 'shadow', 'regular', 'portrait')],
                null],
        ];
        foreach ($starters as [$slug, $name, $description, $theme, $css]) {
            DB::table('storefront_themes')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $description, 'theme' => json_encode($theme + ['preset' => null, 'dark' => null]),
                'custom_css' => $css, 'status' => 'published', 'source' => 'office', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_themes');
    }
};
