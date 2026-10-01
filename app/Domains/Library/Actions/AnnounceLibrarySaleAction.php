<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;

/**
 * COMMERCE_PARITY_PLAN P5 (the owner: "SMS and email to vendor, customer
 * and admin on every purchase"): a paid Library sale tells the reader (their
 * book is ready) and the office. The writer hears from
 * `RecordWriterEarningForPurchaseAction`, in their share. Called once per
 * purchase, from both paths: the BML webhook and the wallet.
 */
class AnnounceLibrarySaleAction
{
    public function execute(LibraryPurchase $purchase): void
    {
        $item = LibraryItem::query()->find($purchase->library_item_id);
        if ($item === null) {
            return;
        }
        $notify = app(NotifyLibraryUserAction::class);
        $notify->execute(
            (int) $purchase->user_id,
            'Your purchase is ready',
            'Payment confirmed for "'.$item->title.'". You can read it now.',
            '/library/'.$item->slug.'/read',
            'purchase_ready',
        );
        $notify->office(
            'A Digital Library sale',
            '"'.$item->title.'" sold for '.($purchase->currency ?: 'MVR').' '.number_format((float) $purchase->amount, 2).'.',
            '/admin/library',
            'new_sale',
        );
    }
}
