<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = \App\Models\School::first();
        
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

        foreach ($subjects as $subject) {
            \App\Models\Subject::create(array_merge($subject, [
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
