<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The seventeen subjects the taxonomy migration seeded (2026_08_25_000027)
 * had an English name only, and the subjects screen adds a subject but does
 * not edit one, so the course catalog named every subject in English on a
 * Dhivehi or Arabic page (C19 slice CT1, STATUS §5ok). This fills the Dhivehi
 * and Arabic names by slug where they are still empty; a name somebody typed
 * stays.
 */
return new class extends Migration
{
    /** slug => [Dhivehi, Arabic] */
    private const NAMES = [
        'quran' => ['ޤުރުއާން', 'القرآن الكريم'],
        'hifz' => ['ހިފްޒު', 'الحفظ'],
        'tajweed' => ['ތަޖްވީދު', 'التجويد'],
        'qiraah' => ['ޤިރާއަތް', 'القراءة'],
        'tafseer' => ['ތަފްސީރު', 'التفسير'],
        'arabic' => ['ޢަރަބި', 'اللغة العربية'],
        'nahw' => ['ނަޙްވު', 'النحو'],
        'sarf' => ['ޞަރްފު', 'الصرف'],
        'balagha' => ['ބަލާޣަތް', 'البلاغة'],
        'conversation' => ['ވާހަކަދެއްކުން', 'المحادثة'],
        'islamic-studies' => ['އިސްލާމީ ދިރާސާ', 'الدراسات الإسلامية'],
        'fiqh' => ['ފިޤްހު', 'الفقه'],
        'aqeedah' => ['ޢަޤީދާ', 'العقيدة'],
        'seerah' => ['ސީރަތް', 'السيرة النبوية'],
        'hadith' => ['ޙަދީޘް', 'الحديث'],
        'dhivehi' => ['ދިވެހި', 'اللغة الديفيهية'],
        'english' => ['އިނގިރޭސި', 'اللغة الإنجليزية'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [$dv, $ar]) {
            foreach (['name_dv' => $dv, 'name_ar' => $ar] as $column => $name) {
                DB::table('course_subjects')
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
                DB::table('course_subjects')->where('slug', $slug)->where($column, $name)->update([$column => null]);
            }
        }
    }
};
