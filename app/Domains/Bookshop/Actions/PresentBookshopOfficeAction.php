<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\ApplyToSellAction;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Support\InsightsReport;
use App\Domains\Bookshop\Support\PrintSalesReport;
use App\Domains\Bookshop\Support\SectionTypes;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use Illuminate\Http\Request;

/**
 * ST2 (admin audit 2026-11-27, STATUS §5uu): the Bookstore office was one
 * page of 19 sections. It is six pages — what needs doing today, the
 * shops, the storefront, the checks, the money, the reports — and each
 * loads only its own lists. Every tab says how many things wait on Today.
 */
class PresentBookshopOfficeAction
{
    /** The office's pages, in the order its tabs show them. */
    public const TABS = ['today', 'shops', 'storefront', 'checks', 'money', 'reports'];

    /** @return array<string, mixed> */
    public function execute(string $tab, Request $request): array
    {
        $slips = fn () => app(ListBankTransferSlipsAction::class)->execute();
        $refunds = fn () => app(ListPendingRefundsAction::class)->execute();
        $applications = fn () => app(DecideVendorApplicationAction::class)->list();
        $listings = fn () => app(ListingApprovalAction::class)->queue();
        $vendors = fn () => app(ListVendorsAction::class)->execute();

        $props = match ($tab) {
            'today' => [
                'slips' => $slips(), 'refunds' => $refunds(), 'applications' => $applications(),
                'applications_open' => app(ApplyToSellAction::class)->isOpen(),
                // COMMERCE_PARITY_PLAN P2: shop owners' identity cards, waiting first; P4: listings awaiting approval, oldest first.
                'identity_checks' => app(IdentityVerificationAction::class)->list('vendor'), 'listings' => $listings(),
                'id_l' => trans('account'),
                'orders' => app(ListOrdersAction::class)->execute(200), 'vendors' => $vendors(),
                'order_statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
                'quotes' => app(ListQuotesAction::class)->summary(),
            ],
            'shops' => [
                'vendors' => $vendors(),
                'default_commission_rate' => number_format((float) config('bookshop.default_commission_rate'), 2, '.', ''),
                'agreement_url' => route('public.page.show', 'vendor-agreement'),
                'sign_in_url' => route('login'),
                'section_types' => array_keys(SectionTypes::TYPES),
                'team' => ['members' => app(ManageBookshopTeamAction::class)->list(), 'can_manage' => (bool) $request->user()?->hasRole('super_admin'), 'added' => $request->session()->get('team_added')],
                'hosts' => ['shops' => app(DecideVendorHostAction::class)->list(), 'shop_host' => config('bookshop.hosts.shop_host'), 'check' => $request->session()->get('host_check')],
            ],
            'storefront' => [
                'home' => app(ManageShopHomeAction::class)->list(),
                'catalogue' => app(ListCatalogueOptionsAction::class)->execute(activeOnly: false),
            ],
            'checks' => [
                'custom_css' => app(DecideStorefrontCssAction::class)->list(),
                'themes' => app(DecideStorefrontThemeAction::class)->list(),
                'reviews' => app(ModerateReviewAction::class)->list(),
                'questions' => app(ModerateQuestionAction::class)->list(),
            ],
            'money' => [
                'money' => app(ListVendorMoneyReportAction::class)->execute(),
                'refunds' => $refunds(),
            ],
            'reports' => [
                'insights' => ['days' => InsightsReport::days((int) $request->query('insight_days', 30)), 'shops' => InsightsReport::byShop((int) $request->query('insight_days', 30)), 'ranges' => array_map('intval', (array) config('bookshop.insights.ranges')), 'print_sales' => PrintSalesReport::byPaperAndMonth(PrintSalesReport::MONTHS, app()->getLocale())],
                'low_stock' => app(ListLowStockAction::class)->execute(500),
            ],
        };

        // What waits on Today, said on every tab: slips to check, refunds and applications to decide, listings to approve.
        $waiting = $tab === 'today'
            ? collect($props['slips'])->where('status', 'waiting')->count() + collect($props['refunds'])->where('status', 'pending')->count()
                + collect($props['applications'])->where('status', 'pending')->count() + count($props['listings'])
            : collect($slips())->where('status', 'waiting')->count() + collect($refunds())->where('status', 'pending')->count()
                + collect($applications())->where('status', 'pending')->count() + count($listings());

        return $props + [
            'tab' => $tab,
            'tabs' => array_map(fn (string $key) => ['key' => $key, 'href' => $key === 'today' ? '/admin/bookshop' : '/admin/bookshop/office/'.$key, 'waiting' => $key === 'today' ? $waiting : 0], self::TABS),
        ];
    }
}
