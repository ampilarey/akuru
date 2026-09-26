<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Services\MeilisearchProductSearch;
use Illuminate\Support\Str;

/**
 * Sends the catalogue to the search server (slice B9e) when the search
 * driver is `meilisearch`: every product for sale, with the fields the v1
 * search looks in, replacing what the index held. Hourly on the schedule
 * while that driver is chosen; nothing to do (null) otherwise.
 */
class SyncProductSearchIndexAction
{
    public const SEARCHABLE = ['title', 'title_dv', 'title_ar', 'sku', 'barcode', 'tags', 'vendor', 'summary', 'description'];

    public function execute(): ?int
    {
        $config = (array) config('bookshop.search.meilisearch');
        if (config('bookshop.search.driver') !== 'meilisearch' || empty($config['host'])) {
            return null;
        }
        $client = app(MeilisearchProductSearch::class)->client($config);
        $index = '/indexes/'.$config['index'];

        $client->delete($index.'/documents');
        $sent = 0;
        ListShopProductsAction::forSale()->with('vendor:id,name')->orderBy('id')->chunk(500, function ($products) use ($client, $index, &$sent) {
            $documents = $products->map(fn (Product $p) => [
                'id' => $p->id,
                'title' => $p->title, 'title_dv' => $p->title_dv, 'title_ar' => $p->title_ar,
                'sku' => $p->sku, 'barcode' => $p->barcode, 'tags' => $p->tags,
                'vendor' => $p->vendor?->name,
                'summary' => $p->summary,
                'description' => Str::limit(trim(strip_tags((string) $p->description)), 2000, ''),
            ])->values()->all();
            $client->post($index.'/documents?primaryKey=id', $documents)->throw();
            $sent += count($documents);
        });
        $client->patch($index.'/settings', ['searchableAttributes' => self::SEARCHABLE])->throw();

        return $sent;
    }
}
