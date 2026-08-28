<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Models\PrayerCategory;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\PrayerTimes\Support\IslandLatinNames;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Imports the Bake&Grill salat.db (the real Maldivian dataset: 42 zone
 * categories × 366 leap-indexed days, 205 islands) as well as any file
 * in the previously supported column shapes. Real-file specifics:
 * island id is `IslandId`, name is `Island`, offset is `Minutes`,
 * activity is `Status`, times use `Fajuru`, and `Date` runs 0–365 —
 * normalized here to this app's 1–366 day_of_year. Latin names are
 * backfilled from the ported curated map when the source has none.
 * Bulk upserts keep a full 15k-row import fast enough for seeding.
 */
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

        // 0-based day column (Bake&Grill `Date`) → shift the whole table to 1–366.
        $dayShift = 0;
        foreach ($times as $row) {
            if ((int) $this->col($row, ['day_of_year', 'dayofyear', 'day', 'doy', 'date']) === 0) {
                $dayShift = 1;
                break;
            }
        }

        $counts = [];
        foreach ($times as $row) {
            $categoryId = (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']);
            $day = (int) $this->col($row, ['day_of_year', 'dayofyear', 'day', 'doy', 'date']) + $dayShift;
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

        return DB::transaction(function () use ($categories, $islands, $times, $dayShift) {
            $now = now();

            $categoryRows = [];
            foreach ($categories as $row) {
                $id = (int) $this->col($row, ['id', '_id']);
                $categoryRows[$id] = ['id' => $id, 'created_at' => $now, 'updated_at' => $now];
            }

            $islandRows = [];
            foreach ($islands as $row) {
                $id = (int) $this->col($row, ['id', '_id', 'islandid', 'island_id']);
                $categoryId = (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']);
                $categoryRows[$categoryId] ??= ['id' => $categoryId, 'created_at' => $now, 'updated_at' => $now];

                $name = (string) $this->col($row, ['name', 'island'], '');
                $atoll = (string) $this->col($row, ['atoll'], '');
                $nameLatin = (string) $this->col($row, ['name_latin', 'namelatin'], '');
                $atollLatin = (string) $this->col($row, ['atoll_latin', 'atolllatin'], '');

                $islandRows[$id] = [
                    'id' => $id,
                    'category_id' => $categoryId,
                    'atoll' => $atoll,
                    'atoll_latin' => $atollLatin !== '' ? $atollLatin : IslandLatinNames::atoll($atoll),
                    'name' => $name,
                    'name_latin' => $nameLatin !== '' ? $nameLatin : IslandLatinNames::island($name),
                    'offset_minutes' => (int) $this->col($row, ['offset_minutes', 'offset', 'minutes'], 0),
                    'latitude' => (float) $this->col($row, ['latitude', 'lat'], 0),
                    'longitude' => (float) $this->col($row, ['longitude', 'lng', 'lon'], 0),
                    'is_active' => (bool) (int) $this->col($row, ['is_active', 'active', 'status'], 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $timeRows = [];
            foreach ($times as $row) {
                $timeRows[] = [
                    'category_id' => (int) $this->col($row, ['category_id', 'categoryid', 'cat_id']),
                    'day_of_year' => (int) $this->col($row, ['day_of_year', 'dayofyear', 'day', 'doy', 'date']) + $dayShift,
                    'fajr' => (int) $this->col($row, ['fajr', 'fajuru']),
                    'sunrise' => (int) $this->col($row, ['sunrise']),
                    'dhuhr' => (int) $this->col($row, ['dhuhr', 'zuhr']),
                    'asr' => (int) $this->col($row, ['asr']),
                    'maghrib' => (int) $this->col($row, ['maghrib']),
                    'isha' => (int) $this->col($row, ['isha']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk(array_values($categoryRows), 200) as $chunk) {
                PrayerCategory::query()->upsert($chunk, ['id'], ['updated_at']);
            }
            foreach (array_chunk(array_values($islandRows), 200) as $chunk) {
                PrayerIsland::query()->upsert(
                    $chunk,
                    ['id'],
                    ['category_id', 'atoll', 'atoll_latin', 'name', 'name_latin', 'offset_minutes', 'latitude', 'longitude', 'is_active', 'updated_at'],
                );
            }
            foreach (array_chunk($timeRows, 500) as $chunk) {
                PrayerTime::query()->upsert(
                    $chunk,
                    ['category_id', 'day_of_year'],
                    ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha', 'updated_at'],
                );
            }

            app(BumpPrayerTimesCacheVersionAction::class)->execute();

            return [
                'categories' => count($categoryRows),
                'islands' => count($islandRows),
                'times' => count($timeRows),
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
