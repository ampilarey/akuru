<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\ListVendorsAction;
use App\Domains\Bookshop\Actions\SaveCatalogueTermAction;
use App\Domains\Bookshop\Actions\UpdateVendorAction;
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
            'default_commission_rate' => number_format((float) config('bookshop.default_commission_rate'), 2, '.', ''),
            'agreement_url' => route('public.page.show', 'vendor-agreement'),
            'sign_in_url' => route('login'),
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
        }, 'bookshop-vendors.csv', ['Content-Type' => 'text/csv']);
    }
}
