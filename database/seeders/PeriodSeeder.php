<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Period;
use App\Models\School;

class PeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::first();
        
        if (!$school) {
            $this->command->warn('No school found. Please run SchoolSeeder first.');
            return;
        }

        $periods = [
            [
                'name' => 'Morning Assembly',
                'name_arabic' => 'الاجتماع الصباحي',
                'name_dhivehi' => 'ހުންނަ އެކުވެރިވުން',
                'start_time' => '07:30',
                'end_time' => '07:45',
                'order' => 1,
                'is_break' => false,
            ],
            [
                'name' => 'Period 1',
                'name_arabic' => 'الحصة الأولى',
                'name_dhivehi' => 'ފުރަތަމަ ހިސާ',
                'start_time' => '07:45',
                'end_time' => '08:30',
                'order' => 2,
                'is_break' => false,
            ],
            [
                'name' => 'Period 2',
                'name_arabic' => 'الحصة الثانية',
                'name_dhivehi' => 'ދެވަނަ ހިސާ',
                'start_time' => '08:30',
                'end_time' => '09:15',
                'order' => 3,
                'is_break' => false,
            ],
            [
                'name' => 'Break',
                'name_arabic' => 'استراحة',
                'name_dhivehi' => 'ހުށަހަޅާ',
                'start_time' => '09:15',
                'end_time' => '09:30',
                'order' => 4,
                'is_break' => true,
            ],
            [
                'name' => 'Period 3',
                'name_arabic' => 'الحصة الثالثة',
                'name_dhivehi' => 'ތިންވަނަ ހިސާ',
                'start_time' => '09:30',
                'end_time' => '10:15',
                'order' => 5,
                'is_break' => false,
            ],
            [
                'name' => 'Period 4',
                'name_arabic' => 'الحصة الرابعة',
                'name_dhivehi' => 'ހަތަރުވަނަ ހިސާ',
                'start_time' => '10:15',
                'end_time' => '11:00',
                'order' => 6,
                'is_break' => false,
            ],
            [
                'name' => 'Lunch Break',
                'name_arabic' => 'استراحة الغداء',
                'name_dhivehi' => 'ރުކުރުން ހުށަހަޅާ',
                'start_time' => '11:00',
                'end_time' => '11:30',
                'order' => 7,
                'is_break' => true,
            ],
            [
                'name' => 'Period 5',
                'name_arabic' => 'الحصة الخامسة',
                'name_dhivehi' => 'ފަހަތުވަނަ ހިސާ',
                'start_time' => '11:30',
                'end_time' => '12:15',
                'order' => 8,
                'is_break' => false,
            ],
            [
                'name' => 'Period 6',
                'name_arabic' => 'الحصة السادسة',
                'name_dhivehi' => 'ހަތަރުވަނަ ހިސާ',
                'start_time' => '12:15',
                'end_time' => '13:00',
                'order' => 9,
                'is_break' => false,
            ],
            [
                'name' => 'Dhuhr Prayer',
                'name_arabic' => 'صلاة الظهر',
                'name_dhivehi' => 'ހުކުރު ނަމާދު',
                'start_time' => '13:00',
                'end_time' => '13:15',
                'order' => 10,
                'is_break' => true,
            ],
            [
                'name' => 'Period 7',
                'name_arabic' => 'الحصة السابعة',
                'name_dhivehi' => 'ހަތަރުވަނަ ހިސާ',
                'start_time' => '13:15',
                'end_time' => '14:00',
                'order' => 11,
                'is_break' => false,
            ],
            [
                'name' => 'Period 8',
                'name_arabic' => 'الحصة الثامنة',
                'name_dhivehi' => 'ހަތަރުވަނަ ހިސާ',
                'start_time' => '14:00',
                'end_time' => '14:45',
                'order' => 12,
                'is_break' => false,
            ],
        ];

        foreach ($periods as $periodData) {
            Period::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'order' => $periodData['order']
                ],
                array_merge($periodData, ['school_id' => $school->id])
            );
        }
    }
}