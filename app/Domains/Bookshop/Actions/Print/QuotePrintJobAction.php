<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintSheet;
use App\Domains\Bookshop\Support\Tax;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.6 (NT3): the price of a job, worked out here and only
 * here — the browser shows it, never computes it. Per sheet: the paper in
 * colour or in black and white, plus each finishing. Times the sheets. Less
 * the largest quantity tier the sheets reach. Prices include GST (Bookstore
 * decision 4); the breakdown names the GST inside the total.
 *
 * Refuses, in the page's language: a paper that is not offered or has no
 * price for the chosen colour, a finishing that is not offered, fewer sheets
 * than the desk's minimum, a paper of another sheet size than the layout's
 * (PP1).
 */
class QuotePrintJobAction
{
    /**
     * @param  list<int>  $finishingIds
     * @return array{paper: array{id: int, name: string, price: float}, colour: bool, finishing: list<array{id: int, name: string, price: float, cuts_sheet: bool}>, per_sheet: float, sheets: int, subtotal: float, tier_percent: float, discount: float, total: float, unit_price: float, gst: float, currency: string, min_sheets: int}
     */
    public function execute(?int $paperId, bool $colour, array $finishingIds, int $sheets, string $locale = 'en', ?string $sheetSize = null, ?int $printerId = null): array
    {
        $settings = PrintDesk::settings();
        // PP7a: only the printer's own papers and finishings price its jobs.
        $paper = $paperId === null ? null : PrintPaperOption::query()->ofPrinter($printerId)->where('is_active', true)->find($paperId);
        if ($paper === null) {
            throw ValidationException::withMessages(['paper' => __('print.error_job_paper')]);
        }
        if ($sheetSize !== null && $paper->sheet_size !== $sheetSize) {
            throw ValidationException::withMessages(['paper' => __('print.error_job_paper_size', ['size' => PrintSheet::label($sheetSize)])]);
        }
        $paperPrice = (float) ($colour ? $paper->price_colour : $paper->price_bw);
        if ($paperPrice <= 0) {
            throw ValidationException::withMessages(['paper' => __($colour ? 'print.error_job_paper_no_colour' : 'print.error_job_paper_no_bw')]);
        }

        $finishing = [];
        $ids = array_values(array_unique(array_map('intval', $finishingIds)));
        if ($ids !== []) {
            $rows = PrintFinishingOption::query()->ofPrinter($printerId)->where('is_active', true)->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get();
            if ($rows->count() !== count($ids)) {
                throw ValidationException::withMessages(['finishing' => __('print.error_job_finishing')]);
            }
            foreach ($rows as $row) {
                $finishing[] = ['id' => $row->id, 'name' => $row->nameIn($locale), 'price' => (float) $row->price, 'cuts_sheet' => (bool) $row->cuts_sheet];
            }
        }

        if ($sheets < $settings['min_sheets']) {
            throw ValidationException::withMessages(['sheets' => __('print.error_job_min_sheets', ['min' => $settings['min_sheets']])]);
        }

        $perSheet = round($paperPrice + array_sum(array_column($finishing, 'price')), 2);
        $subtotal = round($perSheet * $sheets, 2);
        $tierPercent = 0.0;
        foreach ($settings['tiers'] as $tier) {
            if ($sheets >= $tier['sheets']) {
                $tierPercent = (float) $tier['percent'];
            }
        }
        $discount = round($subtotal * $tierPercent / 100, 2);
        $total = round($subtotal - $discount, 2);

        return [
            'paper' => ['id' => $paper->id, 'name' => $paper->nameIn($locale), 'price' => $paperPrice],
            'colour' => $colour,
            'finishing' => $finishing,
            'per_sheet' => $perSheet,
            'sheets' => $sheets,
            'subtotal' => $subtotal,
            'tier_percent' => $tierPercent,
            'discount' => $discount,
            'total' => $total,
            // The cart's line is quantity = sheets at this price (decision D15).
            'unit_price' => round($total / max(1, $sheets), 2),
            'gst' => Tax::inclusive($total, 'standard'),
            'currency' => 'MVR',
            'min_sheets' => $settings['min_sheets'],
        ];
    }
}
