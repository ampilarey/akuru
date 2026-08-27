<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            [
                'key' => 'trust.accreditation',
                'value' => json_encode([
                    'en' => '',
                    'dv' => '',
                    'ar' => '',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'label' => 'Trilingual registration / accreditation line (hero)',
            ],
            [
                'key' => 'trust.founded_year',
                'value' => '2020',
                'type' => 'string',
                'label' => 'Institute founded year (years operating = current year − this)',
            ],
            [
                'key' => 'trust.years_operating',
                'value' => '',
                'type' => 'string',
                'label' => 'Optional years-operating override; blank = compute from founded year',
            ],
            [
                'key' => 'trust.students_taught',
                'value' => '',
                'type' => 'string',
                'label' => 'Optional students-taught override; blank = count unified students',
            ],
            [
                'key' => 'trust.years_label',
                'value' => json_encode([
                    'en' => 'Years operating',
                    'dv' => 'އަހަރު މިސްކިން',
                    'ar' => 'سنوات العمل',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'label' => 'Trilingual label for years operating',
            ],
            [
                'key' => 'trust.students_label',
                'value' => json_encode([
                    'en' => 'Students taught',
                    'dv' => 'ދަރިވަރުން',
                    'ar' => 'الطلاب',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'label' => 'Trilingual label for students taught',
            ],
            [
                'key' => 'trust.partner_logo_ids',
                'value' => '[]',
                'type' => 'json',
                'label' => 'Ordered public media_files ids for partner / affiliation logos',
            ],
        ] as $row) {
            DB::table('settings')->insertOrIgnore([
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => $row['type'],
                'group' => 'trust_settings',
                'label' => $row['label'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'trust_settings')->delete();
    }
};
