<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The five absence reasons E10c seeded (2026_09_11_000008) had an English
 * name only, so a family writing a note from a Dhivehi or Arabic page chose
 * among English reasons, and the office's notes and reasons screens named
 * them in English too (BACKLOG C21, slice OA1, STATUS §5qd). This fills the
 * Dhivehi and Arabic names by code where they are still empty; a name the
 * office typed stays.
 */
return new class extends Migration
{
    /** code => [Dhivehi, Arabic] */
    private const NAMES = [
        'illness' => ['ބަލިވުން', 'مرض'],
        'medical_appointment' => ['ޑޮކްޓަރަށް ދެއްކުން', 'موعد طبي'],
        'family_emergency' => ['އާއިލާގެ ކުއްލި ހާލަތެއް', 'ظرف عائلي طارئ'],
        'religious' => ['ދީނީ ޢިބާދަތެއް', 'مناسبة دينية'],
        'other' => ['އެހެން ސަބަބެއް', 'سبب آخر'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $code => [$dv, $ar]) {
            foreach (['name_dhivehi' => $dv, 'name_arabic' => $ar] as $column => $name) {
                DB::table('absence_types')
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
                DB::table('absence_types')->where('code', $code)->where($column, $name)->update([$column => null]);
            }
        }
    }
};
