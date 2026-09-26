<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Checkout\CashOnDeliveryAction;
use App\Domains\Bookshop\Actions\Checkout\DecideBankTransferSlipAction;
use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Actions\DecideVendorApplicationAction;
use App\Domains\Bookshop\Actions\DecideVendorPayoutAction;
use App\Domains\Bookshop\Actions\ListBankTransferSlipsAction;
use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\ListLowStockAction;
use App\Domains\Bookshop\Actions\ListOrdersAction;
use App\Domains\Bookshop\Actions\ListPendingRefundsAction;
use App\Domains\Bookshop\Actions\ListVendorMoneyReportAction;
use App\Domains\Bookshop\Actions\ListVendorsAction;
use App\Domains\Bookshop\Actions\ManageShopHomeAction;
use App\Domains\Bookshop\Actions\ModerateReviewAction;
use App\Domains\Bookshop\Actions\ModerateStorefrontAction;
use App\Domains\Bookshop\Actions\Money\IssueCommissionInvoicesAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Actions\Orders\RefundOrderAction;
use App\Domains\Bookshop\Actions\SaveBookshopNoticeSwitchesAction;
use App\Domains\Bookshop\Actions\SaveCatalogueTermAction;
use App\Domains\Bookshop\Actions\Shop\ApplyToSellAction;
use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopVendorAction;
use App\Domains\Bookshop\Actions\UpdateVendorAction;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Support\SectionTypes;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Carbon\Carbon;
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
            'money' => app(ListVendorMoneyReportAction::class)->execute(),
            'reviews' => app(ModerateReviewAction::class)->list(),
            'home' => app(ManageShopHomeAction::class)->list(),
            'low_stock' => app(ListLowStockAction::class)->execute(500),
            'notices' => NotifyBookshopUserAction::officeSwitches(),
            'order_statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            'applications' => app(DecideVendorApplicationAction::class)->list(),
            'applications_open' => app(ApplyToSellAction::class)->isOpen(),
            'cod_on' => app(CashOnDeliveryAction::class)->isOn(),
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

    /** B6 (§7 "Payouts"): paid by bank transfer, with its reference, or declined with a note. */
    public function decidePayout(Request $request, int $payout): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'decision' => 'required|string|in:paid,rejected',
            'reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        app(DecideVendorPayoutAction::class)->execute($payout, (int) $request->user()->id, $data['decision'] === 'paid', $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'paid' ? __('shop.payout_paid_flash') : __('shop.payout_rejected_flash'));
    }

    /** B6 (§8): Akuru's commission tax invoices for a month, on demand. */
    public function issueInvoices(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['month' => 'required|date_format:Y-m']);

        $issued = app(IssueCommissionInvoicesAction::class)->execute(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth(), (int) $request->user()->id);

        return back()->with('success', __('shop.invoices_issued_flash', ['count' => count($issued), 'month' => $data['month']]));
    }

    /** A commission invoice, on one page for the printer, for the office. */
    public function invoice(Request $request, int $invoice): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Bookshop/CommissionInvoice', [
            't' => trans('shop'),
            'invoice' => app(ListVendorMoneyReportAction::class)->invoice($invoice),
            'back_url' => route('admin.bookshop.index'),
        ]);
    }

    /** Every listing gets a CSV (conventions): payouts, or the tax report. */
    public function exportMoney(Request $request, string $what): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        abort_unless(in_array($what, ['payouts', 'tax-report', 'balances'], true), 404);
        $report = app(ListVendorMoneyReportAction::class)->execute();

        return response()->streamDownload(function () use ($report, $what): void {
            $out = fopen('php://output', 'w');
            if ($what === 'payouts') {
                Csv::put($out, ['id', 'vendor', 'amount', 'currency', 'status', 'requested_at', 'decided_at', 'reference', 'note']);
                foreach ([...$report['requests'], ...$report['payouts']] as $p) {
                    Csv::put($out, [$p['id'], $p['vendor'], $p['amount'], $p['currency'], $p['status'], $p['requested_at'], $p['decided_at'], $p['reference'], $p['note']]);
                }
            } elseif ($what === 'balances') {
                Csv::put($out, ['vendor', 'commission_rate', 'orders', 'awaiting_delivery', 'in_window', 'available', 'requested', 'paid', 'requestable', 'lifetime_net', 'lifetime_commission']);
                foreach ($report['vendors'] as $v) {
                    Csv::put($out, [$v['name'], $v['commission_rate'], $v['orders_count'], $v['awaiting_delivery'], $v['in_window'], $v['available'], $v['requested'], $v['paid'], $v['requestable_money'], $v['lifetime_net'], $v['lifetime_commission']]);
                }
            } else {
                Csv::put($out, ['month', 'orders', 'sales_charged', 'gross_paid', 'refunded', 'commission', 'commission_tax', 'vendor_net', 'invoices', 'invoiced']);
                foreach ($report['tax_report'] as $r) {
                    Csv::put($out, [$r['month'], $r['orders'], $r['sales'], $r['gross_paid'], $r['refunded'], $r['commission'], $r['commission_tax'], $r['vendor_net'], $r['invoices'], $r['invoiced']]);
                }
            }
            fclose($out);
        }, 'bookstore-'.$what.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** B7 (§4 "office may hide"): hide a review with a note, or publish one. */
    public function moderateReview(Request $request, int $review): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['action' => 'required|string|in:hide,publish', 'note' => 'nullable|string|max:500']);

        app(ModerateReviewAction::class)->execute($review, $data['action'], (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', __('shop.review_'.$data['action'].'_flash'));
    }

    /** B7 (§7): a hero slide, a featured product or a featured collection on the shop home. */
    public function saveHomeFeature(Request $request, ?int $feature = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $kb = (int) config('bookshop.storefront.images.max_kilobytes', 5120);
        $data = $request->validate([
            'kind' => 'nullable|string|in:hero,product,collection',
            'product_id' => 'nullable|integer',
            'vendor_collection_id' => 'nullable|integer',
            'heading' => 'nullable|string|max:160', 'heading_dv' => 'nullable|string|max:160', 'heading_ar' => 'nullable|string|max:160',
            'subheading' => 'nullable|string|max:300', 'subheading_dv' => 'nullable|string|max:300', 'subheading_ar' => 'nullable|string|max:300',
            'link' => 'nullable|array', 'link.kind' => 'nullable|string|in:vendor,category,product,collection', 'link.target' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|file|image|max:'.$kb,
        ]);

        app(ManageShopHomeAction::class)->save($data, $feature, (int) $request->user()->id, $request->file('image'));

        return back()->with('success', __('shop.home_saved_flash'));
    }

    public function removeHomeFeature(Request $request, int $feature): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        app(ManageShopHomeAction::class)->remove($feature);

        return back()->with('success', __('shop.home_removed_flash'));
    }

    public function moveHomeFeature(Request $request, int $feature): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['direction' => 'required|integer|in:-1,1']);

        app(ManageShopHomeAction::class)->move($feature, (int) $data['direction']);

        return back();
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
        $rows = app(ListOrdersAction::class)->execute((int) config('bookshop.operations.export_max_rows', 20000), $this->orderFilters($request));

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

    /** B8: one row per order line across every shop, same filters as the orders export. */
    public function exportOrderLines(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListOrdersAction::class)->lines($this->orderFilters($request), (int) config('bookshop.operations.export_max_rows', 20000));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'vendor', 'placed_at', 'paid_at', 'sku', 'title', 'variant', 'quantity', 'unit_price', 'line_total', 'tax_class', 'tax_amount', 'currency']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['number'], $r['status'], $r['vendor'], $r['placed_at'], $r['paid_at'], $r['sku'], $r['title'], $r['variant'], $r['quantity'], $r['unit_price'], $r['line_total'], $r['tax_class'], $r['tax_amount'], $r['currency']]);
            }
            fclose($out);
        }, 'bookstore-order-lines.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** B8: low stock across every shop, as a CSV. */
    public function exportLowStock(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListLowStockAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['vendor', 'product', 'variant', 'sku', 'stock', 'low_stock_at', 'state', 'status']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['vendor'], $r['title'], $r['variant'], $r['sku'], $r['stock'], $r['low_stock_at'], $r['state'], $r['status']]);
            }
            fclose($out);
        }, 'bookstore-low-stock.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** B9a: approve a shop application (the shop is created) or decline it with a note. */
    public function decideApplication(Request $request, int $application): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'decision' => 'required|string|in:approve,decline',
            'note' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'code' => 'nullable|string|size:3|alpha_num',
        ]);

        $decided = app(DecideVendorApplicationAction::class)->execute($application, (int) $request->user()->id, $data['decision'] === 'approve', $data['note'] ?? null, $data);

        return back()->with('success', __($data['decision'] === 'approve' ? 'shop.application_approved_flash' : 'shop.application_declined_flash', ['shop' => $decided->shop_name]));
    }

    /** B9b: cash on delivery on or off for the whole bookstore. */
    public function setCod(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['on' => 'required|boolean']);

        app(CashOnDeliveryAction::class)->setOn((bool) $data['on']);

        return back()->with('success', __($data['on'] ? 'shop.cod_on_flash' : 'shop.cod_off_flash'));
    }

    /** B9a: open or close the "Open a shop" form. */
    public function setApplicationsOpen(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['open' => 'required|boolean']);

        app(ApplyToSellAction::class)->setOpen((bool) $data['open']);

        return back()->with('success', __($data['open'] ? 'shop.applications_opened_flash' : 'shop.applications_closed_flash'));
    }

    /** Every listing gets a CSV (conventions): the shop applications. */
    public function exportApplications(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(DecideVendorApplicationAction::class)->list(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['submitted', 'status', 'shop', 'legal_name', 'tin', 'applicant', 'email', 'phone', 'island', 'sells', 'link', 'decided', 'note', 'shop_address']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['submitted_at'], $r['status'], $r['shop_name'], $r['legal_name'], $r['tin'], $r['applicant'], $r['contact_email'], $r['contact_phone'], $r['island'], $r['what_they_sell'], $r['link'], $r['decided_at'], $r['decision_note'], $r['vendor']['slug'] ?? '']);
            }
            fclose($out);
        }, 'bookstore-shop-applications.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** B8 (§7 Settings "email/SMS notice switches"). */
    public function saveNotices(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['customer_email' => 'nullable|boolean', 'customer_sms' => 'nullable|boolean', 'vendor_email' => 'nullable|boolean', 'vendor_sms' => 'nullable|boolean']);

        app(SaveBookshopNoticeSwitchesAction::class)->execute($data);

        return back()->with('success', __('shop.notices_saved_flash'));
    }

    /**
     * @return array{vendor?: int, status?: string, from?: string, to?: string}
     */
    private function orderFilters(Request $request): array
    {
        return array_filter($request->validate([
            'vendor' => 'nullable|integer',
            'status' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()))],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]), fn ($v) => $v !== null && $v !== '');
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
