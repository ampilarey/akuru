<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Print\PrunePrintDraftsAction;
use Illuminate\Console\Command;

/** NAME_TAGS_PLAN §7.2 (D13), daily beside the abandoned-cart reminder: print drafts nobody ordered in 30 days go. */
class PrunePrintDraftsCommand extends Command
{
    protected $signature = 'print:prune-drafts {--dry-run : Count what would go and remove nothing}';

    protected $description = 'Remove print designs left as drafts or unapproved proofs for 30 days, never in a basket or an order';

    public function handle(PrunePrintDraftsAction $prune): int
    {
        if ($this->option('dry-run')) {
            $this->line('Print drafts that would be removed: '.$prune->candidates()->count());

            return self::SUCCESS;
        }

        $this->line('Print drafts removed: '.$prune->execute());

        return self::SUCCESS;
    }
}
