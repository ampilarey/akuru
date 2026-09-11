<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SchoolSeeder::class,
            PeriodSeeder::class,
            SubjectSeeder::class,
            ClassSeeder::class,
            UserSeeder::class,
            PageSeeder::class,
            CourseCategorySeeder::class,
            CourseSeeder::class,
            PilotRehearsalSeeder::class,
            PrayerTimesDatabaseSeeder::class,

            // Without this, `surahs` is empty on a fresh install and the
            // halaqa sheet's "From surah…" / "To surah…" pickers render with no
            // options at all — so the new-memorization lane cannot be recorded
            // until somebody runs this seeder by hand. Found by walking the F5
            // gate, where the empty picker looked at first like a broken screen.
            //
            // This is the development subset (14 surahs), not the Qur'an
            // dataset: the authoritative data arrives through the mushaf import.
            // `updateOrCreate` keyed on `index` makes it safe to re-run.
            SurahSeeder::class,
        ]);
    }
}
