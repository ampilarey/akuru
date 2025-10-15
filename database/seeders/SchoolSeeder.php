<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\School::create([
            'name' => 'Akuru Institute',
            'name_arabic' => 'معهد أكورو',
            'name_dhivehi' => 'އަކުރު އިންސްޓިޓިއުޓް',
            'description' => 'Islamic and Arabic Education Institute',
            'description_arabic' => 'معهد التعليم الإسلامي والعربي',
            'description_dhivehi' => 'އިސްލާމް އަދި ޢަރަބި ތައުލީމް އިންސްޓިޓިއުޓް',
            'address' => 'Malé, Maldives',
            'phone' => '+960 123-4567',
            'email' => 'info@akuru.edu.mv',
            'website' => 'https://akuru.edu.mv',
            'principal_name' => 'Dr. Ahmed Ibrahim',
            'principal_name_arabic' => 'د. أحمد إبراهيم',
            'principal_name_dhivehi' => 'ޑރ. އަހްމަދު އިބްރާހިމް',
            'established_year' => '2010',
            'is_active' => true,
        ]);
    }
}
