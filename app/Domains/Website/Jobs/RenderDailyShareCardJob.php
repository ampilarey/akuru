<?php

namespace App\Domains\Website\Jobs;

use App\Domains\Website\Actions\GenerateShareCardAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RenderDailyShareCardJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $dailyContentId) {}

    public function handle(GenerateShareCardAction $action): void
    {
        try {
            $row = DailyContent::query()->find($this->dailyContentId);
            if ($row === null) {
                return;
            }
            $status = $row->status instanceof DailyContentStatus ? $row->status : DailyContentStatus::tryFrom((string) $row->status);
            if ($status !== DailyContentStatus::Published) {
                return;
            }
            $action->execute($row);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
