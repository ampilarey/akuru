<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\QuoteItem;
use App\Domains\Bookshop\Models\QuoteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk quotes for schools, the shop's side (slice B9d): the requests for
 * this shop, a price for each line and how many days it holds, or a
 * decline with a note. A price may be above the list price (a special
 * order) or below it; zero is refused. Only this shop's quotes.
 */
class VendorQuotesAction
{
    /** The statuses a quote can be in, for the portal's filter. */
    public const STATUSES = QuoteRequest::STATUSES;

    /**
     * @return array{quotes: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function list(VendorScope $scope, ?string $status = null): array
    {
        $base = QuoteRequest::query()->where('vendor_id', $scope->vendorId);
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
        $quotes = (clone $base)->when($status !== null && $status !== '', fn ($q) => $q->where('status', $status))
            ->with(['vendor:id,name,slug', 'items'])->orderByRaw("case when status = 'requested' then 0 else 1 end")->orderByDesc('id')->limit(300)->get();
        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $quotes->pluck('user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return [
            'quotes' => $quotes->map(fn (QuoteRequest $q) => CustomerQuotesAction::present($q) + [
                'customer' => $people->get($q->user_id)?->name, 'customer_email' => $people->get($q->user_id)?->email,
            ])->values()->all(),
            'counts' => $counts,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $prices  quote item id => unit price
     */
    public function quote(VendorScope $scope, int $quoteId, array $prices, int $validDays, ?string $note): QuoteRequest
    {
        $max = (int) config('bookshop.quotes.max_valid_days', 60);
        if ($validDays < 1 || $validDays > $max) {
            throw ValidationException::withMessages(['valid_days' => __('shop.error_quote_days', ['max' => $max])]);
        }
        $quote = DB::transaction(function () use ($scope, $quoteId, $prices, $validDays, $note) {
            $quote = QuoteRequest::query()->where('vendor_id', $scope->vendorId)->whereKey($quoteId)->lockForUpdate()->firstOrFail();
            if (! in_array($quote->status, ['requested', 'quoted'], true)) {
                throw ValidationException::withMessages(['quote' => __('shop.error_quote_decided')]);
            }
            $total = 0.0;
            foreach ($quote->items()->get() as $line) {
                /** @var QuoteItem $line */
                $price = $prices[$line->id] ?? $prices[(string) $line->id] ?? null;
                if (! is_numeric($price) || (float) $price <= 0) {
                    throw ValidationException::withMessages(['prices' => __('shop.error_quote_price', ['title' => $line->title])]);
                }
                $line->update(['quoted_price' => round((float) $price, 2)]);
                $total += round((float) $price, 2) * $line->quantity;
            }
            $quote->update([
                'status' => 'quoted', 'quoted_total' => round($total, 2), 'valid_until' => now()->addDays($validDays)->toDateString(),
                'vendor_note' => trim((string) $note) ?: null, 'quoted_by' => $scope->userId, 'quoted_at' => now(),
            ]);

            return $quote->refresh();
        });

        app(NotifyBookshopUserAction::class)->execute((int) $quote->user_id, __('shop.notice_quote_ready_title'),
            __('shop.notice_quote_ready_body', ['number' => $quote->number, 'vendor' => $scope->vendorName, 'amount' => $quote->currency.' '.number_format((float) $quote->quoted_total, 2), 'date' => $quote->valid_until?->toDateString()]),
            '/my-quotes/'.$quote->number, 'quote_ready');

        return $quote;
    }

    public function decline(VendorScope $scope, int $quoteId, string $note): QuoteRequest
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('shop.error_quote_decline_note')]);
        }
        $quote = QuoteRequest::query()->where('vendor_id', $scope->vendorId)->whereKey($quoteId)->firstOrFail();
        if (! in_array($quote->status, ['requested', 'quoted'], true)) {
            throw ValidationException::withMessages(['quote' => __('shop.error_quote_decided')]);
        }
        $quote->update(['status' => 'declined', 'declined_at' => now(), 'vendor_note' => trim($note)]);
        app(NotifyBookshopUserAction::class)->execute((int) $quote->user_id, __('shop.notice_quote_declined_title'),
            __('shop.notice_quote_declined_body', ['number' => $quote->number, 'vendor' => $scope->vendorName, 'note' => trim($note)]), '/my-quotes/'.$quote->number, 'quote_ready');

        return $quote->refresh();
    }
}
