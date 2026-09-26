<?php

namespace App\Domains\Bookshop\Http\Controllers\Concerns;

use App\Domains\Bookshop\Actions\Cart\ResolveCartAction;
use App\Domains\Bookshop\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The one basket this request is about (BOOKSHOP_PLAN §4): the signed-in
 * person's, or a guest's by a token that lives only in their session. The
 * token is minted on the first write, so reading the shop never creates a
 * row.
 */
trait ResolvesCart
{
    protected function cart(Request $request, bool $create = false): ?Cart
    {
        $token = $request->session()->get(ResolveCartAction::SESSION_KEY);
        if ($token === null && $create && $request->user() === null) {
            $token = Str::random(40);
            $request->session()->put(ResolveCartAction::SESSION_KEY, $token);
        }

        return app(ResolveCartAction::class)->execute($request->user()?->id, $token, $create);
    }
}
