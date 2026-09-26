<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\VendorInsightsAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B9e: the shop's funnel (`/vendor/insights`) — visits,
 * product views, adds to cart, checkouts and paid orders over 7, 30 or 90
 * days, the products most looked at and the shop's pages. Thin: the shop
 * and the numbers come through the `VendorScope`.
 */
class VendorInsightsController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/VendorInsights', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'report' => app(VendorInsightsAction::class)->report($scope, (int) $request->query('days', 30)),
            'ranges' => array_map('intval', (array) config('bookshop.insights.ranges')),
        ]);
    }

    /** Every listing gets a CSV (conventions): the days, or the products. */
    public function export(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $report = app(VendorInsightsAction::class)->report($scope, (int) $request->query('days', 30));
        $products = $request->query('list') === 'products';

        return response()->streamDownload(function () use ($report, $products): void {
            $out = fopen('php://output', 'w');
            $rows = $products ? $report['top_products'] : $report['daily'];
            Csv::put($out, $products ? ['product_id', 'title', 'views', 'cart_adds', 'sold', 'sales'] : ['day', 'shop_views', 'product_views', 'cart_adds', 'checkouts', 'orders_paid', 'revenue']);
            foreach ($rows as $row) {
                Csv::put($out, array_values($row));
            }
            fclose($out);
        }, 'insights-'.$scope->vendorSlug.'-'.($products ? 'products' : 'days').'-'.$report['days'].'d.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
