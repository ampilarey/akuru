<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\PrayerRangeBlockDTO;
use App\Domains\PrayerTimes\DTOs\PrayerRangeComparisonDTO;
use Carbon\Carbon;

class ComparePrayerTimesAcrossRangeAction
{
    public function execute(int $islandId, Carbon $from, Carbon $to): PrayerRangeComparisonDTO
    {
        $from = $from->copy()->timezone(config('app.timezone'))->startOfDay();
        $to = $to->copy()->timezone(config('app.timezone'))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $resolver = app(ResolvePrayerTimesForIslandAction::class);
        $blocks = [];
        $cursor = $from->copy();
        $currentKey = null;
        $blockStart = null;
        $blockTimes = [];
        $blockEnd = null;

        while ($cursor->lte($to)) {
            $dto = $resolver->execute($islandId, $cursor);
            $key = implode('|', array_map(fn ($v) => $v ?? '', $dto->minutes()));
            if ($currentKey === null) {
                $currentKey = $key;
                $blockStart = $cursor->toDateString();
                $blockTimes = $dto->times();
            } elseif ($key !== $currentKey) {
                $blocks[] = new PrayerRangeBlockDTO($blockStart, $blockEnd, $blockTimes);
                $currentKey = $key;
                $blockStart = $cursor->toDateString();
                $blockTimes = $dto->times();
            }
            $blockEnd = $cursor->toDateString();
            $cursor->addDay();
        }

        if ($blockStart !== null) {
            $blocks[] = new PrayerRangeBlockDTO($blockStart, $blockEnd, $blockTimes);
        }

        return new PrayerRangeComparisonDTO(
            islandId: $islandId,
            from: $from->toDateString(),
            to: $to->toDateString(),
            blocks: $blocks,
        );
    }
}
