<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Shop\ApplyToSellAction;
use App\Domains\Bookshop\Actions\Vendor\AcceptVendorAgreementAction;
use App\Domains\Bookshop\Actions\Vendor\ImportVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\ListVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\ListVendorSubscribersAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorDiscountCodesAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorMembersAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorDeliveryMethodsAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorNoticeSettingsAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorShopSettingsAction;
use App\Domains\Bookshop\Enums\DeliveryKind;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Domains\Bookshop\Support\ProductSheet;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B1a: the vendor portal (`/vendor`). New UI, so
 * Inertia. Thin: every rule — which vendor, which rows, who may add people
 * — is in `VendorScope` and the Actions that take it.
 */
class VendorPortalController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response|RedirectResponse
    {
        // B9a: someone with no shop is offered the application, not a 403.
        if (! app(ApplyToSellAction::class)->belongsToAShop((int) $request->user()->id)) {
            return redirect()->route('vendor.apply');
        }
        $scope = $this->authorizeVendor($request, needsAgreement: false);
        $filters = $this->productFilters($request);
        $page = $scope->agreementAccepted ? app(ListVendorProductsAction::class)->page($scope, $filters, (int) ($filters['page'] ?? 1)) : null;

        return Inertia::render('Bookshop/Vendor', [
            't' => trans('shop'),
            'vendor' => [
                'id' => $scope->vendorId,
                'name' => $scope->vendorName,
                'slug' => $scope->vendorSlug,
                'role' => $scope->role->value,
                'agreement_accepted' => $scope->agreementAccepted,
            ],
            'memberships' => app(ResolveVendorScopeAction::class)->memberships($scope->userId),
            'agreement_url' => route('public.page.show', 'vendor-agreement'),
            'products' => $page['rows'] ?? [],
            'products_page' => $page === null ? null : array_diff_key($page, ['rows' => true]),
            'members' => $scope->agreementAccepted ? app(ManageVendorMembersAction::class)->list($scope) : [],
            'delivery_methods' => $scope->agreementAccepted ? app(SaveVendorDeliveryMethodsAction::class)->list($scope) : [],
            'delivery_kinds' => array_map(fn (DeliveryKind $k) => $k->value, DeliveryKind::cases()),
            'shop_settings' => $scope->agreementAccepted ? app(SaveVendorShopSettingsAction::class)->get($scope) : null,
            'discount_codes' => $scope->agreementAccepted ? app(ManageVendorDiscountCodesAction::class)->list($scope) : [],
            'notice_settings' => $scope->agreementAccepted ? app(SaveVendorNoticeSettingsAction::class)->get($scope) : null,
            'newsletter' => $scope->agreementAccepted ? app(ListVendorSubscribersAction::class)->summary($scope) : null,
            'options' => app(ListCatalogueOptionsAction::class)->execute(),
            'filters' => $filters + ['q' => null, 'status' => null, 'low' => null, 'category' => null],
            'must_set_password' => (bool) $request->user()->force_password_change,
            'set_password_url' => route('account.set-password'),
        ]);
    }

    /**
     * B8: the product list's search, filters and page.
     *
     * @return array<string, mixed>
     */
    private function productFilters(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:draft,active,archived',
            'low' => 'nullable|boolean',
            'category' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
        ]);
    }

    public function acceptAgreement(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request, needsAgreement: false);
        $request->validate(['accept' => 'accepted']);

        app(AcceptVendorAgreementAction::class)->execute($scope);

        return redirect()->route('vendor.index')->with('success', __('shop.agreement_accepted_flash'));
    }

    /** The switcher: act for another shop this person belongs to. */
    public function switchVendor(Request $request): RedirectResponse
    {
        $this->authorizeVendor($request, needsAgreement: false);
        $data = $request->validate(['vendor_id' => 'required|integer']);

        $scope = app(ResolveVendorScopeAction::class)->execute((int) $request->user()->id, (int) $data['vendor_id']);
        abort_if($scope === null || $scope->vendorId !== (int) $data['vendor_id'], 403);
        $request->session()->put(self::SESSION_VENDOR, $scope->vendorId);

        return redirect()->route('vendor.index');
    }

    public function addMember(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless($scope->isOwner(), 403, __('shop.owner_only'));
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:40',
        ]);

        $result = app(ManageVendorMembersAction::class)->addStaff($scope, $data);

        return back()
            ->with('success', __('shop.member_added_flash'))
            ->with('temporary_password', $result['temporary_password']);
    }

    /** B2: the owner replaces the shop's delivery methods as a whole. */
    public function saveDeliveryMethods(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless($scope->isOwner(), 403, __('shop.owner_only'));
        $data = $request->validate([
            'methods' => 'present|array|max:12',
            'methods.*.id' => 'nullable|integer',
            'methods.*.kind' => 'required|string|max:20',
            'methods.*.name' => 'nullable|string|max:120',
            'methods.*.name_dv' => 'nullable|string|max:120',
            'methods.*.name_ar' => 'nullable|string|max:120',
            'methods.*.fee' => 'nullable|numeric|min:0|max:100000',
            'methods.*.free_over' => 'nullable|numeric|min:0|max:1000000',
            'methods.*.minimum_order' => 'nullable|numeric|min:0|max:1000000',
            'methods.*.carrier_paid_on_arrival' => 'nullable|boolean',
            'methods.*.handling_days' => 'nullable|integer|min:0|max:60',
            'methods.*.note' => 'nullable|string|max:255',
            'methods.*.is_active' => 'nullable|boolean',
        ]);

        app(SaveVendorDeliveryMethodsAction::class)->replace($scope, $data['methods']);

        return back()->with('success', __('shop.delivery_saved_flash'));
    }

    /** B3: returns window and conditions, and holiday mode. Owners only. */
    public function saveShopSettings(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless($scope->isOwner(), 403, __('shop.owner_only'));
        $data = $request->validate([
            'return_window_days' => 'required|integer|min:1|max:365',
            'return_conditions' => 'nullable|string|max:2000',
            'holiday_from' => 'nullable|date',
            'holiday_until' => 'nullable|date',
            'holiday_notice' => 'nullable|string|max:255',
            'free_delivery_over' => 'nullable|numeric|min:0|max:1000000',
            'cod_enabled' => 'nullable|boolean',
            'cod_max' => 'nullable|numeric|min:0|max:1000000',
        ]);

        app(SaveVendorShopSettingsAction::class)->save($scope, $data);

        return back()->with('success', __('shop.settings_saved_flash'));
    }

    /** B7 (§6.5): a discount code the shop funds, on its own products only. Owners only. */
    public function saveDiscountCode(Request $request, ?int $code = null): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'nullable|string|max:120',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01|max:100000',
            'max_discount_amount' => 'nullable|numeric|min:0|max:100000',
            'minimum_order_amount' => 'nullable|numeric|min:0|max:1000000',
            'usage_limit' => 'nullable|integer|min:1|max:100000',
            'per_user_limit' => 'nullable|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        app(ManageVendorDiscountCodesAction::class)->save($scope, $data, $code);

        return back()->with('success', __('shop.code_saved_flash', ['code' => strtoupper($data['code'])]));
    }

    public function setDiscountCodeStatus(Request $request, int $code): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['active' => 'required|boolean']);

        app(ManageVendorDiscountCodesAction::class)->setStatus($scope, $code, (bool) $data['active']);

        return back()->with('success', __($data['active'] ? 'shop.code_on_flash' : 'shop.code_off_flash'));
    }

    /** B9c: the shop's newsletter list, with each person's unsubscribe link for the shop's mailings. */
    public function exportSubscribers(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorSubscribersAction::class)->all($scope);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['email', 'name', 'consented_at', 'unsubscribe_url']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['email'], $r['name'], $r['consented_at'], $r['unsubscribe_url']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-newsletter.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** B8: which shop notices also go by email or SMS. Owners only (in the Action). */
    public function saveNotices(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['events' => 'required|array', 'events.*.email' => 'nullable|boolean', 'events.*.sms' => 'nullable|boolean']);

        app(SaveVendorNoticeSettingsAction::class)->save($scope, $data['events']);

        return back()->with('success', __('shop.notices_saved_flash'));
    }

    /** B2: the office's template becomes the shop's own rows, to edit. */
    public function useDeliveryTemplate(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless($scope->isOwner(), 403, __('shop.owner_only'));

        app(SaveVendorDeliveryMethodsAction::class)->template($scope);

        return back()->with('success', __('shop.delivery_saved_flash'));
    }

    /**
     * Every listing gets a CSV (conventions): the vendor's products — since
     * B8 in the product sheet's layout, so the same file imports back.
     */
    public function exportProducts(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ImportVendorProductsAction::class)->export($scope);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ProductSheet::COLUMNS);
            foreach ($rows as $row) {
                Csv::put($out, $row);
            }
            fclose($out);
        }, $scope->vendorSlug.'-products.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
