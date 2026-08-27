<?php

namespace App\Domains\PrayerTimes\DTOs;

final readonly class IslandDTO
{
    public function __construct(
        public int $id,
        public string $nameEn,
        public string $nameDv,
        public string $nameAr,
        public string $atollLatin,
        public float $latitude,
        public float $longitude,
        public int $offsetMinutes,
        public bool $isActive,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->nameEn,
            'name_dv' => $this->nameDv,
            'name_ar' => $this->nameAr,
            'atoll_latin' => $this->atollLatin,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'offset_minutes' => $this->offsetMinutes,
            'is_active' => $this->isActive,
            'error' => $this->error,
        ];
    }
}
