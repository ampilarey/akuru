<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Orders\CheckoutReceiptAction;
use App\Http\Controllers\Controller;

/**
 * COMMERCE_PARITY_PLAN P8: the receipt a paid checkout's SMS links to. The
 * token is the whole of the right; it shows what a paper receipt shows.
 */
class ShopReceiptController extends Controller
{
    public function show(string $token)
    {
        $receipt = app(CheckoutReceiptAction::class)->find($token);
        abort_if($receipt === null, 404);

        return response()->view('public.shop.receipt', ['receipt' => $receipt])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
