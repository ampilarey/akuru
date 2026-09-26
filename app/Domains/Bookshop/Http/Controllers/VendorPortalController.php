<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Vendor\AcceptVendorAgreementAction;
use App\Domains\Bookshop\Actions\Vendor\ListVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorMembersAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
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

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request, needsAgreement: false);
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:draft,active,archived',
        ]);

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
            'products' => $scope->agreementAccepted ? app(ListVendorProductsAction::class)->execute($scope, $filters) : [],
            'members' => $scope->agreementAccepted ? app(ManageVendorMembersAction::class)->list($scope) : [],
            'options' => app(ListCatalogueOptionsAction::class)->execute(),
            'filters' => $filters + ['q' => null, 'status' => null],
            'must_set_password' => (bool) $request->user()->force_password_change,
            'set_password_url' => route('account.set-password'),
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

    /** Every listing gets a CSV (conventions): the vendor's products. */
    public function exportProducts(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorProductsAction::class)->execute($scope, [], 5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'sku', 'barcode', 'category', 'brand', 'price', 'compare_at_price', 'tax_class', 'track_stock', 'stock', 'status', 'visibility', 'variants', 'photos', 'updated_at']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['id'], $row['title'], $row['sku'], $row['barcode'], $row['category'], $row['brand'],
                    $row['price'], $row['compare_at_price'], $row['tax_class'], $row['track_stock'] ? 'yes' : 'no',
                    $row['stock'], $row['status'], $row['visibility'], count($row['variants']), count($row['images']), $row['updated_at'],
                ]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-products.csv', ['Content-Type' => 'text/csv']);
    }
}
