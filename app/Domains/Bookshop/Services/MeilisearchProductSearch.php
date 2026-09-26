<?php

namespace App\Domains\Bookshop\Services;

use App\Domains\Bookshop\Contracts\ProductSearchInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A Meilisearch server behind the search contract (§10 "a later binding
 * behind a contract if the catalogue grows", slice B9e) — over its HTTP
 * API, no SDK. It returns the matching product ids in relevance order
 * (typo-tolerant, all three languages); the listing still applies every
 * other filter and the "for sale" rule, so the index may lag without a
 * product ever showing that should not. When the server does not answer
 * the database search takes over for that request, and it is logged.
 * `SyncProductSearchIndexAction` fills the index.
 */
class MeilisearchProductSearch implements ProductSearchInterface
{
    public function __construct(private readonly DatabaseProductSearch $fallback) {}

    public function name(): string
    {
        return 'meilisearch';
    }

    public function apply(Builder $query, string $words, bool $rank): Builder
    {
        $ids = $this->ids($words);
        if ($ids === null) {
            return $this->fallback->apply($query, $words, $rank);
        }
        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }
        $query->whereIn($query->getModel()->qualifyColumn('id'), $ids);

        return $rank ? $query->orderByRaw('field('.$query->getModel()->qualifyColumn('id').', '.implode(',', $ids).')') : $query;
    }

    /**
     * The matching ids, best first; null when the server could not be asked.
     *
     * @return list<int>|null
     */
    public function ids(string $words): ?array
    {
        $config = (array) config('bookshop.search.meilisearch');
        if (empty($config['host'])) {
            return null;
        }
        try {
            $response = $this->client($config)->post('/indexes/'.$config['index'].'/search', [
                'q' => $words, 'limit' => (int) ($config['limit'] ?? 1000), 'attributesToRetrieve' => ['id'],
            ]);
            if (! $response->successful()) {
                Log::warning('Bookstore search: Meilisearch answered '.$response->status().'; using the database search.');

                return null;
            }

            return array_values(array_map(fn ($hit) => (int) $hit['id'], (array) $response->json('hits', [])));
        } catch (Throwable $e) {
            Log::warning('Bookstore search: Meilisearch unreachable ('.$e->getMessage().'); using the database search.');

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function client(array $config): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) $config['host'], '/'))->timeout((int) ($config['timeout'] ?? 3))->acceptJson();

        return empty($config['key']) ? $client : $client->withToken((string) $config['key']);
    }
}
