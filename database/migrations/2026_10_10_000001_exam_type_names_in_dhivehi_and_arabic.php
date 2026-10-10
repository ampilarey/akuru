<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six exam types S31 seeded (2026_08_25_000009) had an English name
 * only, so the exam schedule's forms and the assessment weights named them
 * in English on a Dhivehi or Arabic page (BACKLOG C21, slice EG1, STATUS
 * §5qj). This fills the Dhivehi and Arabic names by code where they are
 * still empty; a name the office typed stays.
 */
return new class extends Migration
{
    /** code => [Dhivehi, Arabic] */
    private const NAMES = [
        'midterm' => ['މެދު ޓާމްގެ އިމްތިޙާން', 'اختبار منتصف الفصل'],
        'final' => ['ފައިނަލް އިމްތިޙާން', 'الاختبار النهائي'],
        'quiz' => ['ކުއިޒް', 'اختبار قصير'],
        'assignment' => ['އެސައިންމަންޓް', 'واجب'],
        'practical' => ['ޕްރެކްޓިކަލް', 'اختبار عملي'],
        'oral' => ['ދުލުން ކުރާ އިމްތިޙާން', 'اختبار شفهي'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $code => [$dv, $ar]) {
            foreach (['name_dhivehi' => $dv, 'name_arabic' => $ar] as $column => $name) {
                DB::table('exam_types')
                    ->where('code', $code)
                    ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $name]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $code => [$dv, $ar]) {
            foreach (['name_dhivehi' => $dv, 'name_arabic' => $ar] as $column => $name) {
                DB::table('exam_types')->where('code', $code)->where($column, $name)->update([$column => null]);
            }
        }
    }
};
