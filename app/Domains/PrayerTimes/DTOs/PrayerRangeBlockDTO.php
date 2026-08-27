<?php

namespace App\Domains\PrayerTimes\DTOs;

final readonly class PrayerRangeBlockDTO
{
    /**
     * @param  array<string, string|null>  $times
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $times,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'times' => $this->times,
        ];
    }
}
