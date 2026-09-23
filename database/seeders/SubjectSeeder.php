<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = \App\Domains\Settings\Models\School::first();

        $subjects = [
            [
                'name' => 'Quran Memorization',
                'name_arabic' => 'حفظ القرآن الكريم',
                'name_dhivehi' => 'ޤުރުއާން ހަފްޒު',
                'code' => 'QUR101',
                'type' => 'Quran',
                'is_quran_subject' => true,
            ],
            [
                'name' => 'Quran Recitation',
                'name_arabic' => 'تلاوة القرآن الكريم',
                'name_dhivehi' => 'ޤުރުއާން ތިލާވަތް',
                'code' => 'QUR102',
                'type' => 'Quran',
                'is_quran_subject' => true,
            ],
            [
                'name' => 'Arabic Language',
                'name_arabic' => 'اللغة العربية',
                'name_dhivehi' => 'ޢަރަބި ބަހުން',
                'code' => 'ARB101',
                'type' => 'Arabic',
                'is_quran_subject' => false,
            ],
            [
                'name' => 'Islamic Studies',
                'name_arabic' => 'الدراسات الإسلامية',
                'name_dhivehi' => 'އިސްލާމް ތައުލީމް',
                'code' => 'ISL101',
                'type' => 'Islamic Studies',
                'is_quran_subject' => false,
            ],
            [
                'name' => 'Hadith Studies',
                'name_arabic' => 'دراسة الحديث',
                'name_dhivehi' => 'ހަދީޘް ތައުލީމް',
                'code' => 'HAD101',
                'type' => 'Islamic Studies',
                'is_quran_subject' => false,
            ],
            [
                'name' => 'Fiqh (Islamic Jurisprudence)',
                'name_arabic' => 'الفقه الإسلامي',
                'name_dhivehi' => 'ފިގްހު',
                'code' => 'FIQ101',
                'type' => 'Islamic Studies',
                'is_quran_subject' => false,
            ],
        ];

        // By code, which is unique: this can run on a database that already
        // has some of them, and `PilotRehearsalSeeder` calls it for the three
        // it needs (STATUS §5fz — staging had none, and stopped on the first).
        foreach ($subjects as $subject) {
            \App\Domains\Academics\Models\Subject::query()->firstOrCreate(['code' => $subject['code']], array_merge($subject, [
                'school_id' => $school->id,
                'description' => 'Islamic education subject',
                'description_arabic' => 'مادة تعليمية إسلامية',
                'description_dhivehi' => 'އިސްލާމް ތައުލީމް މާދާ',
                'credits' => 1,
                'is_active' => true,
            ]));
        }
    }
}
