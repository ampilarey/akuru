<?php

namespace App\Domains\PrayerTimes\DTOs;

use Carbon\Carbon;

final readonly class PrayerTimesDTO
{
    public function __construct(
        public bool $available,
        public ?int $islandId,
        public string $nameEn,
        public string $nameDv,
        public string $nameAr,
        public string $date,
        public ?string $fajr,
        public ?string $sunrise,
        public ?string $dhuhr,
        public ?string $asr,
        public ?string $maghrib,
        public ?string $isha,
        public ?int $fajrMinutes = null,
        public ?int $sunriseMinutes = null,
        public ?int $dhuhrMinutes = null,
        public ?int $asrMinutes = null,
        public ?int $maghribMinutes = null,
        public ?int $ishaMinutes = null,
        public ?string $error = null,
    ) {}

    public static function unavailable(string $error, ?int $islandId = null, string $date = ''): self
    {
        return new self(
            available: false,
            islandId: $islandId,
            nameEn: '',
            nameDv: '',
            nameAr: '',
            date: $date,
            fajr: null,
            sunrise: null,
            dhuhr: null,
            asr: null,
            maghrib: null,
            isha: null,
            error: $error,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function times(): array
    {
        return [
            'fajr' => $this->fajr,
            'sunrise' => $this->sunrise,
            'dhuhr' => $this->dhuhr,
            'asr' => $this->asr,
            'maghrib' => $this->maghrib,
            'isha' => $this->isha,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    public function minutes(): array
    {
        return [
            'fajr' => $this->fajrMinutes,
            'sunrise' => $this->sunriseMinutes,
            'dhuhr' => $this->dhuhrMinutes,
            'asr' => $this->asrMinutes,
            'maghrib' => $this->maghribMinutes,
            'isha' => $this->ishaMinutes,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->available && $other->available && $this->minutes() === $other->minutes();
    }

    /**
     * @return array{prayer: ?string, time: ?string, is_prayer_time: bool}
     */
    public function currentPrayer(?Carbon $now = null): array
    {
        if (! $this->available) {
            return ['prayer' => null, 'time' => null, 'is_prayer_time' => false];
        }

        $now = $now ?? now();
        foreach ($this->times() as $name => $hhmm) {
            if ($hhmm === null) {
                continue;
            }
            $time = $now->copy()->setTimeFromTimeString($hhmm);
            if ($now->between($time, $time->copy()->addMinutes(30))) {
                return ['prayer' => $name, 'time' => $hhmm, 'is_prayer_time' => true];
            }
        }

        return ['prayer' => null, 'time' => null, 'is_prayer_time' => false];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'island_id' => $this->islandId,
            'name_en' => $this->nameEn,
            'name_dv' => $this->nameDv,
            'name_ar' => $this->nameAr,
            'date' => $this->date,
            'times' => $this->times(),
            'minutes' => $this->minutes(),
            'error' => $this->error,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCached(array $payload): self
    {
        $times = $payload['times'] ?? [];
        $minutes = $payload['minutes'] ?? [];

        return new self(
            available: (bool) ($payload['available'] ?? false),
            islandId: isset($payload['island_id']) ? (int) $payload['island_id'] : null,
            nameEn: (string) ($payload['name_en'] ?? ''),
            nameDv: (string) ($payload['name_dv'] ?? ''),
            nameAr: (string) ($payload['name_ar'] ?? ''),
            date: (string) ($payload['date'] ?? ''),
            fajr: $times['fajr'] ?? null,
            sunrise: $times['sunrise'] ?? null,
            dhuhr: $times['dhuhr'] ?? null,
            asr: $times['asr'] ?? null,
            maghrib: $times['maghrib'] ?? null,
            isha: $times['isha'] ?? null,
            fajrMinutes: isset($minutes['fajr']) ? (int) $minutes['fajr'] : null,
            sunriseMinutes: isset($minutes['sunrise']) ? (int) $minutes['sunrise'] : null,
            dhuhrMinutes: isset($minutes['dhuhr']) ? (int) $minutes['dhuhr'] : null,
            asrMinutes: isset($minutes['asr']) ? (int) $minutes['asr'] : null,
            maghribMinutes: isset($minutes['maghrib']) ? (int) $minutes['maghrib'] : null,
            ishaMinutes: isset($minutes['isha']) ? (int) $minutes['isha'] : null,
            error: $payload['error'] ?? null,
        );
    }
}
