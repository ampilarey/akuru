<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six levels the taxonomy migration seeded (2026_08_25_000027) had an
 * English name only, like its subjects (2026_10_08_000001), so the glossary's
 * level list read in English on a Dhivehi or Arabic page (C19 slice CT3,
 * STATUS §5on). This fills the Dhivehi and Arabic names by slug where they are
 * still empty; a name somebody typed stays.
 */
return new class extends Migration
{
    /** slug => [Dhivehi, Arabic] */
    private const NAMES = [
        'foundation' => ['ފައުންޑޭޝަން', 'التأسيسي'],
        'beginner' => ['ފެށޭ ފަންތި', 'المبتدئ'],
        'intermediate' => ['މެދު ފަންތި', 'المتوسط'],
        'advanced' => ['މަތީ ފަންތި', 'المتقدم'],
        'level-1' => ['ލެވެލް 1', 'المستوى 1'],
        'level-2' => ['ލެވެލް 2', 'المستوى 2'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [$dv, $ar]) {
            foreach (['name_dv' => $dv, 'name_ar' => $ar] as $column => $name) {
                DB::table('course_levels')
                    ->where('slug', $slug)
                    ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $name]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $slug => [$dv, $ar]) {
            foreach (['name_dv' => $dv, 'name_ar' => $ar] as $column => $name) {
                DB::table('course_levels')->where('slug', $slug)->where($column, $name)->update([$column => null]);
            }
        }
    }
};
