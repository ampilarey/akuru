<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ResolveLibraryAccessAction;
use App\Domains\Library\Actions\StartLibraryCheckoutAction;
use App\Domains\Library\Models\LibraryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * L3 checkout endpoints. The return page only DISPLAYS state — the grant
 * comes from the webhook listener, never from here (§43.5).
 */
class LibraryCheckoutController extends Controller
{
    public function checkout(Request $request, string $slug)
    {
        abort_unless($request->user() !== null, 403);

        $result = app(StartLibraryCheckoutAction::class)->execute(
            $slug,
            (int) $request->user()->id,
            route('public.library.payment-return', $slug),
        );

        if ($result['redirect_url'] !== null) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()
            ->route('public.library.show', $slug)
            ->with('error', $result['error'] ?? 'Payment could not be started.');
    }

    public function paymentReturn(Request $request, string $slug)
    {
        $item = LibraryItem::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();
        $access = app(ResolveLibraryAccessAction::class)->execute($item, $request->user()?->id);

        return view('public.library.payment-return', [
            'item' => ['title' => $item->title, 'slug' => $item->slug],
            'confirmed' => $access['can_read'],
        ]);
    }
}
