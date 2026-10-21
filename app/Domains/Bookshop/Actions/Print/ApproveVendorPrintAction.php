<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\PrintProducts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRINT_PRODUCTS_PLAN PP7a (PD10a, PD10c): the office approves a shop to print,
 * or stops it. Approved, the shop has its own copy of each print product, on
 * sale — so its print sales are its orders, earning at its commission.
 * Stopped, those copies go back to draft and no new job may choose it;
 * jobs already ordered are its to finish.
 */
class ApproveVendorPrintAction
{
    public function execute(Vendor $vendor, bool $approve): Vendor
    {
        if ($vendor->kind === 'akuru') {
            throw ValidationException::withMessages(['vendor' => __('print.error_vendor_print_akuru')]);
        }
        if ($approve && $vendor->status !== VendorStatus::Active) {
            throw ValidationException::withMessages(['vendor' => __('print.error_vendor_print_inactive')]);
        }

        return DB::transaction(function () use ($vendor, $approve): Vendor {
            $vendor->forceFill(['print_enabled' => $approve])->save();
            foreach (PrintProducts::kinds() as $kind) {
                $product = PrintProducts::productFor($kind, $vendor);
                $product?->forceFill(['status' => $approve ? 'active' : 'draft'])->save();
            }

            return $vendor->refresh();
        });
    }

    /**
     * The shops the office may approve, and those it has: every active shop
     * but Akuru's own, with whether it prints and how many papers it prices.
     *
     * @return list<array{id: int, name: string, slug: string, print_enabled: bool, papers: int}>
     */
    public static function shops(): array
    {
        return Vendor::query()->where('kind', '!=', 'akuru')->where(fn ($q) => $q->where('status', 'active')->orWhere('print_enabled', true))
            ->orderBy('name')->get(['id', 'name', 'slug', 'print_enabled'])
            ->map(fn (Vendor $v) => [
                'id' => $v->id, 'name' => $v->name, 'slug' => $v->slug, 'print_enabled' => (bool) $v->print_enabled,
                'papers' => PrintPaperOption::query()->ofPrinter($v->id)->where('is_active', true)->count(),
            ])->values()->all();
    }
}
