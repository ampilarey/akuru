<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Surah;

class SurahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $surahs = $this->getSurahsData();

        foreach ($surahs as $surah) {
            Surah::updateOrCreate(
                ['index' => $surah['index']],
                $surah
            );
        }
    }

    private function getSurahsData()
    {
        return [
            ['index' => 1, 'arabic_name' => 'الفاتحة', 'english_name' => 'Al-Fatihah', 'transliteration' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_place' => 'Meccan', 'juz_start' => 1, 'juz_end' => 1],
            ['index' => 2, 'arabic_name' => 'البقرة', 'english_name' => 'Al-Baqarah', 'transliteration' => 'Al-Baqarah', 'ayah_count' => 286, 'revelation_place' => 'Medinan', 'juz_start' => 1, 'juz_end' => 3],
            ['index' => 3, 'arabic_name' => 'آل عمران', 'english_name' => 'Ali Imran', 'transliteration' => 'Ali Imran', 'ayah_count' => 200, 'revelation_place' => 'Medinan', 'juz_start' => 3, 'juz_end' => 4],
            ['index' => 4, 'arabic_name' => 'النساء', 'english_name' => 'An-Nisa', 'transliteration' => 'An-Nisa', 'ayah_count' => 176, 'revelation_place' => 'Medinan', 'juz_start' => 4, 'juz_end' => 6],
            ['index' => 5, 'arabic_name' => 'المائدة', 'english_name' => 'Al-Maidah', 'transliteration' => 'Al-Maidah', 'ayah_count' => 120, 'revelation_place' => 'Medinan', 'juz_start' => 6, 'juz_end' => 7],
            ['index' => 6, 'arabic_name' => 'الأنعام', 'english_name' => 'Al-Anam', 'transliteration' => 'Al-Anam', 'ayah_count' => 165, 'revelation_place' => 'Meccan', 'juz_start' => 7, 'juz_end' => 8],
            ['index' => 7, 'arabic_name' => 'الأعراف', 'english_name' => 'Al-Araf', 'transliteration' => 'Al-Araf', 'ayah_count' => 206, 'revelation_place' => 'Meccan', 'juz_start' => 8, 'juz_end' => 9],
            ['index' => 8, 'arabic_name' => 'الأنفال', 'english_name' => 'Al-Anfal', 'transliteration' => 'Al-Anfal', 'ayah_count' => 75, 'revelation_place' => 'Medinan', 'juz_start' => 9, 'juz_end' => 10],
            ['index' => 9, 'arabic_name' => 'التوبة', 'english_name' => 'At-Tawbah', 'transliteration' => 'At-Tawbah', 'ayah_count' => 129, 'revelation_place' => 'Medinan', 'juz_start' => 10, 'juz_end' => 11],
            ['index' => 10, 'arabic_name' => 'يونس', 'english_name' => 'Yunus', 'transliteration' => 'Yunus', 'ayah_count' => 109, 'revelation_place' => 'Meccan', 'juz_start' => 11, 'juz_end' => 11],
            // Continue with more surahs...
            ['index' => 112, 'arabic_name' => 'الإخلاص', 'english_name' => 'Al-Ikhlas', 'transliteration' => 'Al-Ikhlas', 'ayah_count' => 4, 'revelation_place' => 'Meccan', 'juz_start' => 30, 'juz_end' => 30],
            ['index' => 113, 'arabic_name' => 'الفلق', 'english_name' => 'Al-Falaq', 'transliteration' => 'Al-Falaq', 'ayah_count' => 5, 'revelation_place' => 'Meccan', 'juz_start' => 30, 'juz_end' => 30],
            ['index' => 114, 'arabic_name' => 'الناس', 'english_name' => 'An-Nas', 'transliteration' => 'An-Nas', 'ayah_count' => 6, 'revelation_place' => 'Meccan', 'juz_start' => 30, 'juz_end' => 30],
        ];
    }
}