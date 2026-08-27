<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;

/**
 * L6 (§7.7, §13): the admin money view — requested payouts to act on and
 * per-writer earning totals for the payout report / CSV.
 */
class ListWriterPayoutReportAction
{
    /**
     * @return array{requests: list<array<string, mixed>>, writers: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        app(MatureWriterEarningsAction::class)->execute();

        $profiles = WriterProfile::query()->get()->keyBy('id');

        $requests = WriterPayout::query()
            ->where('status', 'requested')
            ->orderBy('requested_at')
            ->get()
            ->map(fn (WriterPayout $payout) => [
                'id' => $payout->id,
                'writer' => $profiles[$payout->writer_id]?->display_name ?? '—',
                'amount' => (float) $payout->amount,
                'currency' => $payout->currency,
                'requested_at' => $payout->requested_at?->toDateTimeString(),
            ])->values()->all();

        $writers = WriterEarning::query()
            ->selectRaw("writer_id,
                SUM(CASE WHEN status = 'pending' THEN writer_amount ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'available' THEN writer_amount ELSE 0 END) as available,
                SUM(CASE WHEN status = 'paid' THEN writer_amount ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'refunded' THEN writer_amount ELSE 0 END) as refunded")
            ->groupBy('writer_id')
            ->get()
            ->map(fn ($row) => [
                'writer' => $profiles[$row->writer_id]?->display_name ?? '—',
                'pending' => round((float) $row->pending, 2),
                'available' => round((float) $row->available, 2),
                'paid' => round((float) $row->paid, 2),
                'refunded' => round((float) $row->refunded, 2),
            ])->values()->all();

        return ['requests' => $requests, 'writers' => $writers];
    }
}
