<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\Blog\StartTipAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BLOG_PLAN BL6a: a signed-in reader tips a blog's writer, by card (to BML)
 * or from their wallet. A guest is sent to sign in. Coming back from BML
 * grants nothing: the tip is paid on the webhook (rule 12).
 */
class BlogTipController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->guest(route('login'));
        }
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
            'item_id' => 'nullable|integer',
            'note' => 'nullable|string|max:300',
            'wallet' => 'nullable|boolean',
        ]);
        $result = app(StartTipAction::class)->execute(
            (int) $request->user()->id, $slug, (float) $data['amount'], isset($data['item_id']) ? (int) $data['item_id'] : null,
            $data['note'] ?? null, (bool) ($data['wallet'] ?? false), route('public.library.author', ['slug' => $slug, 'tip' => 'returned']),
        );

        if ($result['redirect_url'] !== null) {
            return redirect()->away($result['redirect_url']);
        }

        return $result['tip']->status === 'paid'
            ? back()->with('success', __('blog.flash_tip_thanks'))
            : back()->withErrors(['tip' => $result['error'] ?: __('blog.error_tip_failed')]);
    }
}
