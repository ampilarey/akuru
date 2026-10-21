<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Library\Models\LibraryBundle;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Support\Said;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * LIBRARY_PLAN §19 (B4d, STATUS §5un): buy a bundle. One purchase per work,
 * pending, each with its share of the bundle's price by list price (the
 * last takes the rounding), so each writer earns on theirs (§16). By card
 * the grant waits for the bank's webhook (§43.5, rule 12); by wallet it is
 * internal money and grants at once, all in one transaction. The bundle is
 * the offer: no code or campaign applies on top.
 */
class StartLibraryBundleCheckoutAction
{
    /**
     * @return array{purchases: Collection<int, LibraryPurchase>, redirect_url: ?string, error: ?string, paid_with_wallet: bool}
     */
    public function execute(string $slug, int $userId, ?string $returnUrl = null, bool $payWithWallet = false): array
    {
        $bundle = LibraryBundle::query()->where('slug', $slug)->firstOrFail();
        $items = $bundle->items()->where('status', 'published')->where('access_type', 'paid')->where('price', '>', 0)->get();
        if (! $bundle->onSale() || $items->count() < SaveLibraryBundleAction::MIN_ITEMS) {
            throw ValidationException::withMessages(['bundle' => __('public.bundle_error_not_on_sale')]);
        }
        $access = app(ResolveLibraryAccessAction::class);
        if ($items->every(fn (LibraryItem $i) => $access->execute($i, $userId)['can_read'])) {
            throw ValidationException::withMessages(['bundle' => __('public.bundle_error_have_all')]);
        }
        $total = round((float) $bundle->price, 2);
        $shares = self::shares($items, $total);

        if ($payWithWallet) {
            $purchases = DB::transaction(function () use ($bundle, $items, $shares, $userId, $total) {
                $purchases = $this->open($bundle, $items, $shares, $userId);
                app(DebitWalletAction::class)->execute($userId, $total, 'purchase', (int) $purchases->first()->id, 'Library bundle: '.$bundle->title);
                foreach ($purchases as $purchase) {
                    $purchase->forceFill(['status' => 'paid', 'purchased_at' => now()])->save();
                    app(GrantLibraryAccessAction::class)->execute($userId, (int) $purchase->library_item_id, 'wallet', (int) $purchase->id);
                }

                return $purchases;
            });
            foreach ($purchases as $purchase) {
                app(RecordWriterEarningForPurchaseAction::class)->execute((int) $purchase->id);
                app(AnnounceLibrarySaleAction::class)->execute($purchase);
            }

            return ['purchases' => $purchases, 'redirect_url' => null, 'error' => null, 'paid_with_wallet' => true];
        }

        $purchases = $this->open($bundle, $items, $shares, $userId);
        $initiated = app(InitiatePayablePaymentAction::class)->execute('library_bundle', (int) $bundle->id, $userId, $total, $bundle->currency ?: 'MVR', $returnUrl);
        $failed = $initiated['redirect_url'] === null;
        LibraryPurchase::query()->whereIn('id', $purchases->pluck('id'))->update(['payment_id' => $initiated['payment']->id] + ($failed ? ['status' => 'failed'] : []));
        if ($failed) {
            app(NotifyLibraryUserAction::class)->office(
                Said::of('library_notices.payment_failed_title'),
                Said::of('library_notices.payment_not_started_body', ['title' => $bundle->title, 'name' => (string) (config('auth.providers.users.model')::query()->find($userId)?->name ?? '—'), 'amount' => number_format($total, 2)]),
                '/admin/library/bundles',
            );
        }

        return ['purchases' => $purchases->map->refresh(), 'redirect_url' => $initiated['redirect_url'], 'error' => $initiated['error'], 'paid_with_wallet' => false];
    }

    /**
     * Each work's share of the price, by its list price; the last takes the rounding.
     *
     * @param  Collection<int, LibraryItem>  $items
     * @return array<int, float> item id => share
     */
    public static function shares(Collection $items, float $total): array
    {
        $list = (float) $items->sum(fn (LibraryItem $i) => (float) $i->price);
        $shares = [];
        $given = 0.0;
        $last = $items->last();
        foreach ($items as $item) {
            $share = $item->is($last) ? round($total - $given, 2) : round($total * (float) $item->price / $list, 2);
            $shares[(int) $item->id] = $share;
            $given = round($given + $share, 2);
        }

        return $shares;
    }

    /**
     * @param  Collection<int, LibraryItem>  $items
     * @param  array<int, float>  $shares
     * @return Collection<int, LibraryPurchase>
     */
    private function open(LibraryBundle $bundle, Collection $items, array $shares, int $userId): Collection
    {
        return $items->map(fn (LibraryItem $item) => LibraryPurchase::query()->create([
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'library_bundle_id' => $bundle->id,
            'amount' => $shares[(int) $item->id],
            'currency' => $bundle->currency ?: 'MVR',
            'status' => 'pending',
        ]))->values();
    }
}
