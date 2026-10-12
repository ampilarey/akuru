<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Media\Actions\DeleteDocumentsOfAction;
use Illuminate\Support\Facades\DB;

/**
 * NAME_TAGS_PLAN §7.2 and D13 (STATUS §5rx): a design left as a draft or an
 * unapproved proof for 30 days, never put in a basket or an order, is
 * removed with its proof sheets, print file and uploaded artwork. An
 * approved design stays — it is on *My designs* to be ordered, and its price
 * is taken again at approval. Anything a cart line or an order line names
 * is never touched.
 */
class PrunePrintDraftsAction
{
    public const DAYS = 30;

    public function candidates(int $days = self::DAYS): \Illuminate\Database\Eloquent\Builder
    {
        return PrintJob::query()
            ->whereIn('status', [PrintJobStatus::Draft->value, PrintJobStatus::Proofed->value])
            ->where('updated_at', '<', now()->subDays(max(1, $days)))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cart_items')->whereColumn('cart_items.print_job_id', 'print_jobs.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('order_items')->whereColumn('order_items.print_job_id', 'print_jobs.id'));
    }

    public function execute(int $days = self::DAYS): int
    {
        $count = 0;
        $this->candidates($days)->orderBy('id')->chunkById(200, function ($jobs) use (&$count): void {
            foreach ($jobs as $job) {
                DB::transaction(function () use ($job): void {
                    app(DeleteDocumentsOfAction::class)->execute('print_job', (int) $job->id);
                    $job->delete();
                });
                $count++;
            }
        });

        return $count;
    }
}
