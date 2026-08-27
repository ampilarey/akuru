<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;

function seedPrayerTimesFixture(): array
{
    return app(SeedSyntheticPrayerTimesAction::class)->execute();
}

function makePrayerContactUser(?string $phone = '+9607772434'): User
{
    $user = User::factory()->create();
    UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => $phone,
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    return $user;
}

function sqliteSalatFixture(string $path, int $days = 366, int $categories = 1): void
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE Category (id INTEGER PRIMARY KEY)');
    $pdo->exec('CREATE TABLE Island (id INTEGER PRIMARY KEY, category_id INTEGER, atoll TEXT, atoll_latin TEXT, name TEXT, name_latin TEXT, offset_minutes INTEGER, latitude REAL, longitude REAL)');
    $pdo->exec('CREATE TABLE PrayerTimes (category_id INTEGER, day_of_year INTEGER, fajr INTEGER, sunrise INTEGER, dhuhr INTEGER, asr INTEGER, maghrib INTEGER, isha INTEGER)');
    for ($c = 1; $c <= $categories; $c++) {
        $pdo->exec("INSERT INTO Category (id) VALUES ({$c})");
        $lat = 4.1755 + ($c - 1);
        $pdo->exec("INSERT INTO Island (id, category_id, atoll, atoll_latin, name, name_latin, offset_minutes, latitude, longitude) VALUES ({$c}, {$c}, 'Male', 'Male', 'މާލެ', 'Malé {$c}', 0, {$lat}, 73.5093)");
        $insert = $pdo->prepare('INSERT INTO PrayerTimes (category_id, day_of_year, fajr, sunrise, dhuhr, asr, maghrib, isha) VALUES (?,?,?,?,?,?,?,?)');
        for ($day = 1; $day <= $days; $day++) {
            $fajr = 300 + $day;
            $insert->execute([$c, $day, $fajr, $fajr + 30, 735, 930, 1095, 1170]);
        }
    }
}
