<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The eight leave types S52 seeded (2026_08_25_000022) had an English name
 * only, so the leave types, the balances and a member of staff's leave named
 * them in English on a Dhivehi or Arabic page (BACKLOG C21, slice HR1,
 * STATUS §5ql). This fills the Dhivehi and Arabic names by code where they
 * are still empty; a name the office typed stays.
 */
return new class extends Migration
{
    /** code => [Dhivehi, Arabic] */
    private const NAMES = [
        'annual' => ['އަހަރީ ޗުއްޓީ', 'إجازة سنوية'],
        'sick' => ['ބަލި ޗުއްޓީ', 'إجازة مرضية'],
        'family' => ['ޢާއިލީ ޗުއްޓީ', 'إجازة عائلية'],
        'hajj_umrah' => ['ޙައްޖު / ޢުމްރާ ޗުއްޓީ', 'إجازة الحج / العمرة'],
        'maternity' => ['ވިހެއުމުގެ ޗުއްޓީ', 'إجازة الأمومة'],
        'paternity' => ['ބައްޕައިންނަށް ދޭ ޗުއްޓީ', 'إجازة الأبوة'],
        'unpaid' => ['މުސާރަ ނުލިބޭ ޗުއްޓީ', 'إجازة بدون راتب'],
        'other' => ['އެހެނިހެން ޗުއްޓީ', 'إجازة أخرى'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $code => [$dv, $ar]) {
            foreach (['name_dhivehi' => $dv, 'name_arabic' => $ar] as $column => $name) {
                DB::table('leave_types')
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
                DB::table('leave_types')->where('code', $code)->where($column, $name)->update([$column => null]);
            }
        }
    }
};
