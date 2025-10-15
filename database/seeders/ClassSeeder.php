<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = \App\Models\School::first();
        
        $classes = [
            [
                'name' => 'Quran Class A',
                'name_arabic' => 'فصل القرآن أ',
                'name_dhivehi' => 'ޤުރުއާން ކްލާސް އ',
                'section' => 'A',
                'level' => 'Quran',
                'capacity' => 20,
            ],
            [
                'name' => 'Quran Class B',
                'name_arabic' => 'فصل القرآن ب',
                'name_dhivehi' => 'ޤުރުއާން ކްލާސް ބ',
                'section' => 'B',
                'level' => 'Quran',
                'capacity' => 20,
            ],
            [
                'name' => 'Arabic Beginners',
                'name_arabic' => 'المبتدئين في العربية',
                'name_dhivehi' => 'ޢަރަބި ބެގިންނަރުތައް',
                'section' => 'Beginners',
                'level' => 'Arabic',
                'capacity' => 25,
            ],
            [
                'name' => 'Arabic Intermediate',
                'name_arabic' => 'المتوسطين في العربية',
                'name_dhivehi' => 'ޢަރަބި މެޑިއަމް',
                'section' => 'Intermediate',
                'level' => 'Arabic',
                'capacity' => 25,
            ],
            [
                'name' => 'Islamic Studies Level 1',
                'name_arabic' => 'الدراسات الإسلامية المستوى الأول',
                'name_dhivehi' => 'އިސްލާމް ތައުލީމް ލެވަލް 1',
                'section' => 'Level 1',
                'level' => 'Islamic Studies',
                'capacity' => 30,
            ],
        ];

        foreach ($classes as $class) {
            \App\Models\ClassRoom::create(array_merge($class, [
                'school_id' => $school->id,
                'description' => 'Islamic education class',
                'is_active' => true,
            ]));
        }
    }
}
