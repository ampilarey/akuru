<?php

namespace App\Domains\ExamsGrades\Jobs;

use App\Domains\ExamsGrades\Actions\GenerateReportCardsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderReportCardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $reportCardId,
        public string $locale = 'en',
        public ?int $actorId = null,
    ) {}

    public function handle(GenerateReportCardsAction $action): void
    {
        $action->renderOne($this->reportCardId, $this->locale, $this->actorId);
    }
}
