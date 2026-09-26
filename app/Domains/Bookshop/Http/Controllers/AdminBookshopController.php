<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Checkout\DecideBankTransferSlipAction;
use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Actions\ListBankTransferSlipsAction;
use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\ListOrdersAction;
use App\Domains\Bookshop\Actions\ListPendingRefundsAction;
use App\Domains\Bookshop\Actions\ListVendorsAction;
use App\Domains\Bookshop\Actions\ModerateStorefrontAction;
use App\Domains\Bookshop\Actions\Orders\RefundOrderAction;
use App\Domains\Bookshop\Actions\SaveCatalogueTermAction;
use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopVendorAction;
use App\Domains\Bookshop\Actions\UpdateVendorAction;
use App\Domains\Bookshop\Support\SectionTypes;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B1a, the office side (`/admin/bookshop`): invite
 * vendors with their owner, edit and suspend them, and keep the shared
 * categories and brands. `bookshop.manage` gated on the route and here.
 */
class AdminBookshopController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Bookshop/Admin', [
            't' => trans('shop'),
            'vendors' => app(ListVendorsAction::class)->execute(),
            'catalogue' => app(ListCatalogueOptionsAction::class)->execute(activeOnly: false),
            'slips' => app(ListBankTransferSlipsAction::class)->execute(),
            'orders' => app(ListOrdersAction::class)->execute(200),
            'refunds' => app(ListPendingRefundsAction::class)->execute(),
            'default_commission_rate' => number_format((float) config('bookshop.default_commission_rate'), 2, '.', ''),
            'agreement_url' => route('public.page.show', 'vendor-agreement'),
            'sign_in_url' => route('login'),
            'section_types' => array_keys(SectionTypes::TYPES),
        ]);
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:70|alpha_dash',
            'code' => 'nullable|string|size:3|alpha_num',
            'tagline' => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'owner_phone' => 'nullable|string|max:40',
        ]);

        $created = app(CreateVendorAction::class)->execute($data, (int) $request->user()->id);

        return back()
            ->with('success', __('shop.vendor_created_flash', ['name' => $data['name']]))
            ->with('vendor_invite', [
                'vendor' => $data['name'],
                'email' => $data['owner_email'],
                'existing_account' => ! $created['owner_created'],
                'temporary_password' => $created['temporary_password'],
            ]);
    }

    public function updateVendor(Request $request, int $vendor): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:40',
            'gst_registered' => 'nullable|boolean',
            'status' => 'required|string|in:active,suspended',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:1000',
            'opening_hours' => 'nullable|string|max:1000',
            'office_notes' => 'nullable|string|max:5000',
            'badges' => 'nullable|array',
            'badges.*' => 'string|in:'.implode(',', UpdateVendorAction::BADGES),
        ]);

        app(UpdateVendorAction::class)->execute($vendor, $data);

        return back()->with('success', __('shop.vendor_updated_flash'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_dv' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:product_categories,id',
            'sort_order' => 'nullable|integer|min:0|max:10000',
        ]);

        app(SaveCatalogueTermAction::class)->category($data);

        return back()->with('success', __('shop.category_saved_flash'));
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:255']);

        app(SaveCatalogueTermAction::class)->brand($data);

        return back()->with('success', __('shop.brand_saved_flash'));
    }

    /** B2 (decision 7): the office confirms or rejects a bank-transfer slip. */
    public function decideSlip(Request $request, int $slip): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'decision' => 'required|string|in:confirm,reject',
            'note' => 'nullable|string|max:500|required_if:decision,reject',
        ]);

        app(DecideBankTransferSlipAction::class)->execute($slip, $data['decision'] === 'confirm', (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'confirm' ? __('shop.slip_confirmed_flash') : __('shop.slip_rejected_flash'));
    }

    /**
     * B3 (audit finding 6): a card refund, returned through BML's merchant
     * portal and recorded here (`manual`), or credited to the wallet instead.
     */
    public function processRefund(Request $request, int $refund): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'destination' => 'required|string|in:manual,wallet',
            'note' => 'nullable|string|max:500',
        ]);

        app(RefundOrderAction::class)->process($refund, $data['destination'], (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', __('shop.refund_recorded_flash'));
    }

    /** B5 (§6.6): the office requires changes, takes a storefront down, lifts the hold, or locks section types. */
    public function moderateStorefront(Request $request, int $vendor): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'action' => 'required|string|in:'.implode(',', ModerateStorefrontAction::ACTIONS),
            'note' => 'nullable|string|max:1000',
            'locked_types' => 'nullable|array',
            'locked_types.*' => 'string|in:'.implode(',', array_keys(SectionTypes::TYPES)),
        ]);

        app(ModerateStorefrontAction::class)->execute($vendor, $data['action'], (int) $request->user()->id, $data['note'] ?? null, (array) ($data['locked_types'] ?? []));

        return back()->with('success', __('shop.moderation_'.$data['action'].'_flash'));
    }

    /** B5 (§6.6 "it sees the published and draft versions"): the draft, on the real renderer. */
    public function previewStorefront(Request $request, string $vendor)
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $shop = app(PresentShopVendorAction::class)->execute($vendor, draft: true);
        abort_if($shop === null, 404);

        return view('public.shop.index', [
            'home' => null,
            'products' => app(ListShopProductsAction::class)->execute(['vendor' => $vendor], storefront: true),
            'filters' => ['vendor' => $vendor],
            'options' => ['categories' => [], 'brands' => [], 'sorts' => ListShopProductsAction::SORTS],
            'vendor' => $shop,
            'heading' => $shop['name'],
            'preview' => true,
        ]);
    }

    /** Every listing gets a CSV (conventions): the refunds. */
    public function exportRefunds(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListPendingRefundsAction::class)->execute(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['order', 'vendor', 'customer', 'customer_email', 'amount', 'currency', 'paid_with', 'status', 'destination', 'reason', 'payment_id', 'requested_at', 'processed_at']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['order_number'], $r['vendor'], $r['customer'], $r['customer_email'], $r['amount'], $r['currency'], $r['paid_with'], $r['status'], $r['destination'], $r['reason'], $r['bml_reference'], $r['requested_at'], $r['processed_at']]);
            }
            fclose($out);
        }, 'bookstore-refunds.csv', ['Content-Type' => 'text/csv']);
    }

    /** Every listing gets a CSV (conventions): the orders. */
    public function exportOrders(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListOrdersAction::class)->execute(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'vendor', 'customer', 'customer_email', 'payment_method', 'items', 'subtotal', 'discount', 'delivery', 'delivery_fee', 'tax', 'total', 'currency', 'island', 'placed_at', 'paid_at']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['number'], $row['status'], $row['vendor'], $row['customer'], $row['customer_email'], $row['payment_method'], $row['items'],
                    $row['subtotal'], $row['discount'], $row['delivery'], $row['delivery_fee'], $row['tax'], $row['total'], $row['currency'], $row['island'], $row['placed_at'], $row['paid_at'],
                ]);
            }
            fclose($out);
        }, 'bookstore-orders.csv', ['Content-Type' => 'text/csv']);
    }

    /** Every listing gets a CSV (conventions): the vendors. */
    public function exportVendors(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListVendorsAction::class)->execute(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'name', 'slug', 'code', 'status', 'owner', 'owner_email', 'agreement_accepted_at', 'commission_rate', 'products', 'active_products', 'members', 'tin', 'gst_registered', 'created_at']);
            foreach ($rows as $row) {
                $owner = $row['owners'][0] ?? ['name' => null, 'email' => null, 'agreement_accepted_at' => null];
                Csv::put($out, [
                    $row['id'], $row['name'], $row['slug'], $row['code'], $row['status'], $owner['name'], $owner['email'],
                    $owner['agreement_accepted_at'], $row['effective_commission_rate'], $row['products_count'],
                    $row['active_products_count'], $row['members_count'], $row['tin'], $row['gst_registered'] ? 'yes' : 'no', $row['created_at'],
                ]);
            }
            fclose($out);
        }, 'bookstore-vendors.csv', ['Content-Type' => 'text/csv']);
    }
}
