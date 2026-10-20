<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Support\PrintSheet;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §4.2 (NT1): a paper and its two prices. A paper is retired
 * (`is_active` off), never deleted: a job priced on it keeps its name.
 */
class SavePrintPaperAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintPaperOption $paper = null): PrintPaperOption
    {
        $kind = (string) ($data['kind'] ?? 'plain');
        if (! in_array($kind, PrintPaperOption::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('print.error_paper_kind')]);
        }
        $active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $bw = round((float) ($data['price_bw'] ?? 0), 2);
        $colour = round((float) ($data['price_colour'] ?? 0), 2);
        if ($active && $bw <= 0 && $colour <= 0) {
            throw ValidationException::withMessages(['price_bw' => __('print.error_paper_price')]);
        }

        $paper ??= new PrintPaperOption;
        $paper->fill([
            'name' => trim((string) $data['name']),
            'name_dv' => trim((string) ($data['name_dv'] ?? '')) ?: null,
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'gsm' => isset($data['gsm']) && $data['gsm'] !== '' ? (int) $data['gsm'] : null,
            'kind' => $kind,
            // PP1: a paper is stock of one sheet size.
            'sheet_size' => in_array($data['sheet_size'] ?? null, PrintSheet::codes(), true) ? $data['sheet_size'] : ($paper->sheet_size ?? PrintSheet::DEFAULT),
            'price_bw' => $bw,
            'price_colour' => $colour,
            'is_active' => $active,
            'sort_order' => (int) ($data['sort_order'] ?? $paper->sort_order ?? 0),
        ]);
        $paper->save();

        return $paper;
    }
}
