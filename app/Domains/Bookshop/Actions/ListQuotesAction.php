<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction;
use App\Domains\Bookshop\Models\QuoteRequest;

/**
 * The office's view of bulk quotes across every shop (slice B9d): how many
 * are waiting, priced, ordered — and the recent ones with their shop — so
 * a request no shop answers is seen.
 */
class ListQuotesAction
{
    /**
     * @return array{counts: array<string, int>, recent: list<array<string, mixed>>}
     */
    public function summary(int $limit = 20): array
    {
        return [
            'counts' => QuoteRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'recent' => $this->all($limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(int $limit = 5000): array
    {
        return QuoteRequest::query()->with(['vendor:id,name,slug', 'items'])->orderByDesc('id')->limit($limit)->get()
            ->map(fn (QuoteRequest $q) => CustomerQuotesAction::present($q))->values()->all();
    }
}
