<?php

namespace App\Console\Commands;

use App\Domains\HR\Actions\NotifyExpiringDocumentsAction;
use Illuminate\Console\Command;

class NotifyExpiringDocumentsCommand extends Command
{
    protected $signature = 'hr:notify-expiring-documents';

    protected $description = 'Notify HR and staff once at 90/60/30 days before a document expires';

    public function handle(NotifyExpiringDocumentsAction $action): int
    {
        $count = $action->execute();
        $this->info("Sent {$count} document expiry notices.");

        return self::SUCCESS;
    }
}
