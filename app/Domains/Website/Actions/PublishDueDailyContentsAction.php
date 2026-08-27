<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Jobs\RenderDailyShareCardJob;
use App\Domains\Website\Models\DailyContent;

class PublishDueDailyContentsAction
{
    public function execute(?string $today = null): int
    {
        $today = $today ?: now()->timezone(config('app.timezone'))->toDateString();

        $rows = DailyContent::query()
            ->where('status', DailyContentStatus::Scheduled)
            ->whereDate('publish_date', '<=', $today)
            ->whereNotNull('approved_by')
            ->get();

        foreach ($rows as $row) {
            $row->status = DailyContentStatus::Published;
            $row->save();
            RenderDailyShareCardJob::dispatch($row->id);
        }

        return $rows->count();
    }
}
