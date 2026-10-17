<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL3a (STATUS §5si): a blog's page, designed by its writer and
 * checked by the office before it shows (D14).
 *
 *  - `library_blog_themes`: the theme gallery, named in three languages —
 *    colours and shape as data, never CSS. Five to start (§8).
 *  - `writer_profiles` gains the live design (theme, accent, heading font,
 *    tagline, languages, sections) and the waiting one: `pending_page` holds
 *    the whole set of changes, sent for checking (`page_submitted_at`); the
 *    office's note on a refused set is `page_decision_note`.
 *
 * Additive (rule 9). The five themes are reference data the designer needs,
 * not demo content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_blog_themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name_en', 60);
            $table->string('name_dv', 60);
            $table->string('name_ar', 60);
            $table->json('theme');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->string('theme_key', 40)->nullable()->after('blog_title');
            $table->string('accent_color', 7)->nullable()->after('theme_key');
            $table->string('heading_font', 40)->nullable()->after('accent_color');
            $table->string('tagline', 120)->nullable()->after('heading_font');
            $table->json('languages')->nullable()->after('tagline');
            $table->json('sections')->nullable()->after('languages');
            $table->json('pending_page')->nullable()->after('sections');
            $table->timestamp('page_submitted_at')->nullable()->after('pending_page');
            $table->text('page_decision_note')->nullable()->after('page_submitted_at');
        });

        $now = now();
        $themes = [
            ['akuru', 'Akuru', 'އަކުރު', 'أكورو', ['page_bg' => '#FBF7F1', 'card_bg' => '#FFFFFF', 'text' => '#2B1B1E', 'accent' => '#7A1F2B', 'on_accent' => '#FFFFFF', 'radius' => 'soft']],
            ['ocean', 'Ocean', 'ކަނޑު', 'المحيط', ['page_bg' => '#F4FAFC', 'card_bg' => '#FFFFFF', 'text' => '#12303F', 'accent' => '#0B7285', 'on_accent' => '#FFFFFF', 'radius' => 'soft']],
            ['forest', 'Forest', 'ޖަންގަލި', 'الغابة', ['page_bg' => '#F5F8F4', 'card_bg' => '#FFFFFF', 'text' => '#1B2A20', 'accent' => '#1F5F3F', 'on_accent' => '#FFFFFF', 'radius' => 'round']],
            ['sand', 'Sand', 'ވެލި', 'الرمل', ['page_bg' => '#FBF6EE', 'card_bg' => '#FFFFFF', 'text' => '#3A2A1A', 'accent' => '#B0413E', 'on_accent' => '#FFFFFF', 'radius' => 'square']],
            ['night', 'Night', 'ރޭގަނޑު', 'الليل', ['page_bg' => '#14122E', 'card_bg' => '#1F1C40', 'text' => '#F3F0FF', 'accent' => '#F2C778', 'on_accent' => '#1A1400', 'radius' => 'soft']],
        ];
        foreach ($themes as $i => [$key, $en, $dv, $ar, $theme]) {
            DB::table('library_blog_themes')->insert([
                'key' => $key, 'name_en' => $en, 'name_dv' => $dv, 'name_ar' => $ar,
                'theme' => json_encode($theme), 'active' => true, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropColumn(['theme_key', 'accent_color', 'heading_font', 'tagline', 'languages', 'sections', 'pending_page', 'page_submitted_at', 'page_decision_note']);
        });
        Schema::dropIfExists('library_blog_themes');
    }
};
