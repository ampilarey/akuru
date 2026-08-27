<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\Models\PrayerIsland;

class FindNearestIslandAction
{
    public function execute(float $latitude, float $longitude): IslandDTO
    {
        $islands = PrayerIsland::query()->where('is_active', true)->get();
        if ($islands->isEmpty()) {
            return new IslandDTO(
                id: 0,
                nameEn: '',
                nameDv: '',
                nameAr: '',
                atollLatin: '',
                latitude: $latitude,
                longitude: $longitude,
                offsetMinutes: 0,
                isActive: false,
                error: 'No active islands',
            );
        }

        $nearest = null;
        $best = PHP_FLOAT_MAX;
        foreach ($islands as $island) {
            $distance = $this->haversineKm(
                $latitude,
                $longitude,
                (float) $island->latitude,
                (float) $island->longitude,
            );
            if ($distance < $best) {
                $best = $distance;
                $nearest = $island;
            }
        }

        return $this->toDto($nearest);
    }

    public function toDto(PrayerIsland $island): IslandDTO
    {
        return new IslandDTO(
            id: (int) $island->id,
            nameEn: (string) $island->name_latin,
            nameDv: (string) $island->name,
            nameAr: (string) $island->name_latin,
            atollLatin: (string) $island->atoll_latin,
            latitude: (float) $island->latitude,
            longitude: (float) $island->longitude,
            offsetMinutes: (int) $island->offset_minutes,
            isActive: (bool) $island->is_active,
        );
    }

    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }
}
