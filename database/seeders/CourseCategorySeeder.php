<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CourseCategory;

class CourseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Quran Memorization',
                'slug' => 'quran-memorization',
                'order' => 1,
            ],
            [
                'name' => 'Arabic Language',
                'slug' => 'arabic-language',
                'order' => 2,
            ],
            [
                'name' => 'Islamic Studies',
                'slug' => 'islamic-studies',
                'order' => 3,
            ],
            [
                'name' => 'Tajweed',
                'slug' => 'tajweed',
                'order' => 4,
            ],
            [
                'name' => 'Hadith Studies',
                'slug' => 'hadith-studies',
                'order' => 5,
            ],
            [
                'name' => 'Fiqh (Islamic Jurisprudence)',
                'slug' => 'fiqh',
                'order' => 6,
            ],
            [
                'name' => 'Adult Education',
                'slug' => 'adult-education',
                'order' => 7,
            ],
            [
                'name' => 'Children Programs',
                'slug' => 'children-programs',
                'order' => 8,
            ],
        ];

        foreach ($categories as $category) {
            CourseCategory::create($category);
        }
    }
}