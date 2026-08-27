<?php

namespace App\Domains\PrayerTimes\DTOs;

final readonly class PrayerRangeComparisonDTO
{
    /**
     * @param  list<PrayerRangeBlockDTO>  $blocks
     */
    public function __construct(
        public int $islandId,
        public string $from,
        public string $to,
        public array $blocks,
    ) {}

    public function isUniform(): bool
    {
        return count($this->blocks) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'island_id' => $this->islandId,
            'from' => $this->from,
            'to' => $this->to,
            'uniform' => $this->isUniform(),
            'blocks' => array_map(fn (PrayerRangeBlockDTO $block) => $block->toArray(), $this->blocks),
        ];
    }
}
