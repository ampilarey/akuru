<?php

namespace App\Domains\Library\Jobs;

use App\Domains\Library\Actions\RecogniseLibraryItemPagesAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** BACKLOG B1: a few pages of a scanned PDF per run; the next run is queued until the book is read. */
class RecogniseLibraryItemPagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $itemId,
        public int $mediaId,
        public int $fromPage = 1,
    ) {}

    public function handle(RecogniseLibraryItemPagesAction $action): void
    {
        $next = $action->execute($this->itemId, $this->mediaId, $this->fromPage);
        if ($next !== null) {
            self::dispatch($this->itemId, $this->mediaId, $next);
        }
    }
}
