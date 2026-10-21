<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Vendor\FulfilVendorOrderAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintJobReprint;
use App\Domains\Bookshop\Support\AkuruFulfilment;
use App\Domains\Bookshop\Support\PrintDesk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §4.8, §6 (NT4): a print job's life after the cart.
 *
 *  - **ordered** when its order is paid — by the bank's webhook, the
 *    wallet, a confirmed slip — or placed for cash where the desk allows
 *    cash (rule 12: nothing is printed before that);
 *  - **printing** when the desk starts it (the order moves to processing),
 *    **finished** when it is done (a collection order moves to ready, and
 *    its customer is told, once every job on it is finished);
 *  - **reprinted** for a fault — recorded, back to printing, no charge;
 *  - a **problem** puts the order in *needs attention* with the desk's note;
 *  - **cancelled** with its order.
 *
 * Every step writes an order event, so the customer's *My orders* shows it.
 * The order's own states are the Bookstore's; no new one is added (§6.2).
 */
class PrintProductionAction
{
    public const QUEUE = ['ordered', 'printing', 'finished'];

    /** The jobs on a paid (or cash) order become the desk's. */
    public function markOrdered(Order $order): void
    {
        foreach ($this->jobs($order) as $job) {
            if ($job->status === PrintJobStatus::Approved) {
                $job->forceFill(['status' => PrintJobStatus::Ordered, 'ordered_at' => now()])->save();
            }
        }
    }

    /** An order cancelled cancels the jobs not yet printing. */
    public function cancelWith(Order $order): void
    {
        foreach ($this->jobs($order) as $job) {
            if (in_array($job->status, [PrintJobStatus::Approved, PrintJobStatus::Ordered], true)) {
                $job->forceFill(['status' => PrintJobStatus::Cancelled, 'cancelled_at' => now()])->save();
            }
        }
    }

    /** Does the order still have jobs to finish? The order cannot be ready or dispatched before. */
    public static function unfinished(Order $order): bool
    {
        return PrintJob::query()->whereIn('id', OrderItem::query()->where('order_id', $order->id)->whereNotNull('print_job_id')->pluck('print_job_id'))
            ->whereNotIn('status', [PrintJobStatus::Finished->value, PrintJobStatus::Cancelled->value])->exists();
    }

    /**
     * PP7b: is the job on one of this printer's orders? A shop takes the
     * queue's steps, and downloads the print file, only for its own.
     */
    public static function belongsTo(int $jobId, int $vendorId): bool
    {
        return OrderItem::query()->where('print_job_id', $jobId)->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))->exists();
    }

    public function start(int $jobId, int $byUserId): PrintJob
    {
        [$job, $order] = $this->find($jobId);
        if ($job->status !== PrintJobStatus::Ordered) {
            throw ValidationException::withMessages(['job' => __('print.error_queue_step')]);
        }
        DB::transaction(function () use ($job, $order, $byUserId) {
            $job->forceFill(['status' => PrintJobStatus::Printing, 'printing_at' => now(), 'printed_by' => $byUserId])->save();
            $this->event($order, 'print_printing', $byUserId, $job);
        });
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::CashDue, OrderStatus::NeedsAttention], true)) {
            $this->advance($order, 'processing', $byUserId);
        }

        return $job->refresh();
    }

    public function finish(int $jobId, int $byUserId): PrintJob
    {
        [$job, $order] = $this->find($jobId);
        if ($job->status !== PrintJobStatus::Printing) {
            throw ValidationException::withMessages(['job' => __('print.error_queue_step')]);
        }
        DB::transaction(function () use ($job, $order, $byUserId) {
            $job->forceFill(['status' => PrintJobStatus::Finished, 'finished_at' => now(), 'finished_by' => $byUserId])->save();
            $this->event($order, 'print_finished', $byUserId, $job);
        });
        // Every job done: a collection is ready (and the customer told); a delivery waits for the courier step.
        $order->refresh();
        if (! self::unfinished($order) && $order->isCollection() && in_array($order->status, [OrderStatus::Processing, OrderStatus::NeedsAttention], true)) {
            $this->advance($order, 'ready', $byUserId);
        }

        return $job->refresh();
    }

    public function reprint(int $jobId, string $reason, int $byUserId): PrintJob
    {
        [$job, $order] = $this->find($jobId);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('print.error_queue_reason')]);
        }
        if (! in_array($job->status, [PrintJobStatus::Printing, PrintJobStatus::Finished], true) || in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['job' => __('print.error_queue_step')]);
        }
        DB::transaction(function () use ($job, $order, $reason, $byUserId) {
            PrintJobReprint::query()->create(['print_job_id' => $job->id, 'reason' => mb_substr($reason, 0, 500), 'by_user_id' => $byUserId, 'created_at' => now()]);
            $job->forceFill(['status' => PrintJobStatus::Printing, 'printing_at' => now(), 'finished_at' => null, 'finished_by' => null])->save();
            $this->event($order, 'print_reprint', $byUserId, $job);
        });

        return $job->refresh();
    }

    public function problem(int $jobId, string $note, int $byUserId): PrintJob
    {
        [$job, $order] = $this->find($jobId);
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => __('print.error_queue_reason')]);
        }
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Expired], true)) {
            throw ValidationException::withMessages(['job' => __('print.error_queue_step')]);
        }
        DB::transaction(function () use ($job, $order, $note, $byUserId) {
            $order->update(['status' => OrderStatus::NeedsAttention->value]);
            OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'print_problem', 'actor_user_id' => $byUserId, 'created_at' => now(), 'meta' => ['print_job_id' => $job->id, 'note' => mb_substr($note, 0, 500)]]);
        });

        return $job->refresh();
    }

    /**
     * The desk's queue: jobs on paid orders, oldest first, with what the desk
     * needs to print each and when it is due.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(bool $all = false, string $locale = 'en', ?int $vendorId = null): array
    {
        // PP7b: each printer's own — the office's desk Akuru's orders, a shop's desk its own.
        $vendorId ??= PrintDesk::vendor()?->id;
        $lines = OrderItem::query()->whereNotNull('print_job_id')->with(['order', 'printJob.paper'])
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereHas('printJob', fn ($q) => $all ? $q : $q->whereIn('status', self::QUEUE))
            ->whereHas('order', fn ($q) => $all ? $q : $q->whereNotIn('status', ['delivered', 'cancelled', 'expired', 'pending_payment']))
            ->orderBy('id')->limit(500)->get();
        $lead = PrintDesk::settings()['lead_days'];

        return $lines->filter(fn (OrderItem $i) => $i->printJob !== null && $i->order !== null)->map(function (OrderItem $i) use ($lead, $locale) {
            $job = $i->printJob;
            $order = $i->order;
            $price = (array) ($job->approved_price_snapshot ?? []);

            return [
                'id' => $job->id,
                'name' => $job->name,
                'status' => $job->status->value,
                'order' => $order->number,
                'order_status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                // The order's own snapshot, as the Akuru fulfilment list reads it.
                'customer' => $order->address_snapshot['recipient_name'] ?? null,
                'phone' => $order->address_snapshot['phone'] ?? null,
                'delivery' => $order->delivery_name,
                'sheets' => (int) $job->sheets,
                'paper' => $job->paper?->nameIn($locale) ?? ($price['paper']['name'] ?? null),
                'colour' => (bool) $job->colour,
                'finishing' => array_values(array_map(fn ($f) => (string) ($f['name'] ?? ''), (array) ($price['finishing'] ?? []))),
                'total' => (float) $i->line_total,
                'ordered_at' => $job->ordered_at?->format('Y-m-d H:i'),
                'due' => $job->ordered_at === null ? null : self::due($job->ordered_at, $lead)->toDateString(),
                'reprints' => PrintJobReprint::query()->where('print_job_id', $job->id)->count(),
                'file' => $job->print_document_id !== null,
            ];
        })->values()->all();
    }

    /** Working days on from a date — the Maldives' weekend is Friday and Saturday. */
    public static function due(Carbon $from, int $days): Carbon
    {
        $date = $from->copy()->timezone('Indian/Maldives')->startOfDay();
        $left = max(0, $days);
        while ($left > 0) {
            $date->addDay();
            if (! in_array($date->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $left--;
            }
        }

        return $date;
    }

    /** @return list<PrintJob> */
    private function jobs(Order $order): array
    {
        $ids = OrderItem::query()->where('order_id', $order->id)->whereNotNull('print_job_id')->pluck('print_job_id')->all();

        return $ids === [] ? [] : PrintJob::query()->whereIn('id', $ids)->get()->all();
    }

    /** @return array{0: PrintJob, 1: Order} */
    private function find(int $jobId): array
    {
        $job = PrintJob::query()->findOrFail($jobId);
        $line = OrderItem::query()->where('print_job_id', $job->id)->with('order')->latest('id')->first();
        if ($line === null || $line->order === null) {
            throw ValidationException::withMessages(['job' => __('print.error_queue_step')]);
        }

        return [$job, $line->order];
    }

    private function event(Order $order, string $type, int $byUserId, PrintJob $job): void
    {
        OrderEvent::query()->create(['order_id' => $order->id, 'type' => $type, 'actor_user_id' => $byUserId, 'created_at' => now(), 'meta' => ['print_job_id' => $job->id]]);
    }

    private function advance(Order $order, string $to, int $byUserId): void
    {
        $scope = app(ResolveVendorScopeAction::class)->forOffice((int) $order->vendor_id, $byUserId);
        // PP7b: a printing shop moves its own order; the office only what Akuru packs.
        if (! AkuruFulfilment::takesStep($order, $to)) {
            $scope = new VendorScope($scope->vendorId, $byUserId, $scope->role, $scope->vendorName, $scope->vendorSlug, true, $scope->paused);
        }
        app(FulfilVendorOrderAction::class)->advance($scope, (int) $order->id, $to);
    }
}
