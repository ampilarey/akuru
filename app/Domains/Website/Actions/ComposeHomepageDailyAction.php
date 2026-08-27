<?php

namespace App\Domains\Website\Actions;

use App\Domains\Settings\Actions\GetSettingAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Support\Carbon;

class ComposeHomepageDailyAction
{
    /**
     * Today's published daily content, stacked or rotated. Falls back to the
     * most recent published row of that type when today is empty.
     *
     * @return array{layout: string, today: string, items: list<array<string, mixed>>}
     */
    public function execute(?string $today = null): array
    {
        $today = $today ?: now()->timezone(config('app.timezone'))->toDateString();
        $layout = (string) app(GetSettingAction::class)->execute('daily.homepage_layout', 'stacked');
        if ($layout !== 'rotate') {
            $layout = 'stacked';
        }

        $types = $layout === 'rotate'
            ? [$this->rotateType($today)]
            : DailyContentType::cases();

        $lister = app(ListDailyContentsAction::class);
        $items = [];
        foreach ($types as $type) {
            $row = $this->pick($type, $today);
            if ($row === null) {
                continue;
            }
            $item = $lister->present($row);
            $item['is_fallback'] = $row->publish_date?->toDateString() !== $today;
            $items[] = $item;
        }

        return [
            'layout' => $layout,
            'today' => $today,
            'items' => $items,
        ];
    }

    private function rotateType(string $today): DailyContentType
    {
        $cases = DailyContentType::cases();
        $dayOfYear = (int) Carbon::parse($today)->timezone(config('app.timezone'))->dayOfYear;
        $index = ($dayOfYear - 1) % count($cases);

        return $cases[$index];
    }

    private function pick(DailyContentType $type, string $today): ?DailyContent
    {
        $todayRow = DailyContent::query()
            ->where('content_type', $type)
            ->where('status', DailyContentStatus::Published)
            ->whereDate('publish_date', $today)
            ->first();
        if ($todayRow !== null) {
            return $todayRow;
        }

        return DailyContent::query()
            ->where('content_type', $type)
            ->where('status', DailyContentStatus::Published)
            ->orderByDesc('publish_date')
            ->first();
    }
}
