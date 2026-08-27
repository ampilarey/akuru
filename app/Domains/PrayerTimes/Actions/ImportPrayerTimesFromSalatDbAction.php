<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Models\PrayerCategory;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class ImportPrayerTimesFromSalatDbAction
{
    /**
     * @return array{categories: int, islands: int, times: int}
     */
    public function execute(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("salat.db not found at {$path}");
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $categoryTable = $this->tableName($pdo, ['Category', 'Categories', 'prayer_categories']);
        $islandTable = $this->tableName($pdo, ['Island', 'Islands', 'prayer_islands']);
        $timesTable = $this->tableName($pdo, ['PrayerTimes', 'prayer_times', 'Salat']);

        $categories = $this->rows($pdo, $categoryTable);
        $islands = $this->rows($pdo, $islandTable);
        $times = $this->rows($pdo, $timesTable);

        $counts = [];
        foreach ($times as $row) {
            $categoryId = (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']);
            $day = (int) $this->col($row, ['day_of_year', 'dayofyear', 'day', 'doy']);
            $counts[$categoryId][$day] = true;
        }

        $short = [];
        $categoryIds = [];
        foreach ($categories as $row) {
            $id = (int) $this->col($row, ['id', '_id']);
            $categoryIds[$id] = true;
        }
        foreach ($counts as $id => $days) {
            $categoryIds[$id] = true;
        }
        foreach (array_keys($categoryIds) as $id) {
            $n = count($counts[$id] ?? []);
            if ($n !== 366) {
                $short[] = "category {$id} has {$n} rows";
            }
        }
        if ($short !== []) {
            throw new RuntimeException('Import failed: every category must have exactly 366 prayer_times rows. '.implode('; ', $short));
        }

        return DB::transaction(function () use ($categories, $islands, $times) {
            $categoryCount = 0;
            foreach ($categories as $row) {
                $id = (int) $this->col($row, ['id', '_id']);
                PrayerCategory::query()->updateOrCreate(['id' => $id], ['id' => $id]);
                $categoryCount++;
            }

            $islandCount = 0;
            foreach ($islands as $row) {
                $id = (int) $this->col($row, ['id', '_id']);
                $categoryId = (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']);
                PrayerCategory::query()->updateOrCreate(['id' => $categoryId], ['id' => $categoryId]);
                PrayerIsland::query()->updateOrCreate(
                    ['id' => $id],
                    [
                        'category_id' => $categoryId,
                        'atoll' => (string) $this->col($row, ['atoll'], ''),
                        'atoll_latin' => (string) $this->col($row, ['atoll_latin', 'atolllatin'], ''),
                        'name' => (string) $this->col($row, ['name'], ''),
                        'name_latin' => (string) $this->col($row, ['name_latin', 'namelatin'], ''),
                        'offset_minutes' => (int) $this->col($row, ['offset_minutes', 'offset', 'minutes'], 0),
                        'latitude' => (float) $this->col($row, ['latitude', 'lat'], 0),
                        'longitude' => (float) $this->col($row, ['longitude', 'lng', 'lon'], 0),
                        'is_active' => true,
                    ],
                );
                $islandCount++;
            }

            $timeCount = 0;
            foreach ($times as $row) {
                $categoryId = (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']);
                $day = (int) $this->col($row, ['day_of_year', 'dayofyear', 'day', 'doy']);
                PrayerTime::query()->updateOrCreate(
                    ['category_id' => $categoryId, 'day_of_year' => $day],
                    [
                        'fajr' => (int) $this->col($row, ['fajr']),
                        'sunrise' => (int) $this->col($row, ['sunrise']),
                        'dhuhr' => (int) $this->col($row, ['dhuhr', 'zuhr']),
                        'asr' => (int) $this->col($row, ['asr']),
                        'maghrib' => (int) $this->col($row, ['maghrib']),
                        'isha' => (int) $this->col($row, ['isha']),
                    ],
                );
                $timeCount++;
            }

            app(BumpPrayerTimesCacheVersionAction::class)->execute();

            return [
                'categories' => $categoryCount,
                'islands' => $islandCount,
                'times' => $timeCount,
            ];
        });
    }

    /**
     * @param  list<string>  $candidates
     */
    private function tableName(PDO $pdo, array $candidates): string
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $lower = array_map('strtolower', $tables);
        foreach ($candidates as $candidate) {
            $i = array_search(strtolower($candidate), $lower, true);
            if ($i !== false) {
                return $tables[$i];
            }
        }

        throw new RuntimeException('salat.db missing table (looked for '.implode(', ', $candidates).')');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(PDO $pdo, string $table): array
    {
        return $pdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function col(array $row, array $keys, mixed $default = 0): mixed
    {
        $map = [];
        foreach ($row as $key => $value) {
            $map[strtolower((string) $key)] = $value;
        }
        foreach ($keys as $key) {
            $lookup = strtolower($key);
            if (array_key_exists($lookup, $map) && $map[$lookup] !== null && $map[$lookup] !== '') {
                return $map[$lookup];
            }
        }

        return $default;
    }
}
