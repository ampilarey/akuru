<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\Actions\Shop\ProductReviewsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B7: what a signed-in customer keeps in the shop — the
 * wishlist, "notify me when it's back", and reviews of what they received.
 * Public Blade zone, like My orders; every write is the customer's own.
 */
class ShopAccountController extends Controller
{
    public function wishlist(Request $request)
    {
        return view('public.shop.wishlist', ['cards' => app(CustomerListsAction::class)->wishlist((int) $request->user()->id)]);
    }

    public function toggleWishlist(Request $request, string $slug): RedirectResponse
    {
        $saved = app(CustomerListsAction::class)->toggleWishlist((int) $request->user()->id, $slug);

        return back()->with('success', __($saved ? 'shop.wishlist_added_flash' : 'shop.wishlist_removed_flash'));
    }

    public function toggleStockAlert(Request $request, string $slug): RedirectResponse
    {
        $on = app(CustomerListsAction::class)->toggleStockAlert((int) $request->user()->id, $slug);

        return back()->with('success', __($on ? 'shop.alert_on_flash' : 'shop.alert_off_flash'));
    }

    public function review(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:'.(int) config('bookshop.reviews.max_body', 2000),
        ]);

        $review = app(ProductReviewsAction::class)->submit((int) $request->user()->id, $slug, (int) $data['rating'], $data['body'] ?? null);

        return redirect()->to(route('public.shop.product', $slug).'#reviews')
            ->with('success', __($review->status === 'pending' ? 'shop.review_pending_flash' : 'shop.review_thanks_flash'));
    }

    /** Every listing gets a CSV (conventions): the wishlist. */
    public function exportWishlist(Request $request): StreamedResponse
    {
        $rows = app(CustomerListsAction::class)->wishlist((int) $request->user()->id);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['title', 'shop', 'price', 'currency', 'availability', 'url']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['title'], $row['vendor']['name'], $row['price'], $row['currency'], $row['stock']['state'], route('public.shop.product', $row['slug'])]);
            }
            fclose($out);
        }, 'my-wishlist.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
