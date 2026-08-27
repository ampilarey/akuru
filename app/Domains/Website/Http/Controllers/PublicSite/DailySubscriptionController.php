<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Actions\ListDailyContentSubscriptionsAction;
use App\Domains\Website\Actions\SaveDailyContentSubscriptionAction;
use App\Domains\Website\Actions\UnsubscribeDailyContentSubscriptionAction;
use App\Domains\Website\Models\DailyContentSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailySubscriptionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user() !== null, 401);

        return view('public.daily.subscribe', [
            'subscriptions' => app(ListDailyContentSubscriptionsAction::class)->forUser((int) $request->user()->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 401);

        app(SaveDailyContentSubscriptionAction::class)->execute((int) $request->user()->id, $request->all());

        return redirect()
            ->route('public.daily.subscribe')
            ->with('success', 'Subscription saved. You will only receive messages you opted into.');
    }

    public function pause(Request $request, DailyContentSubscription $subscription): RedirectResponse
    {
        abort_unless($request->user() !== null, 401);

        app(UnsubscribeDailyContentSubscriptionAction::class)
            ->pauseOwn((int) $request->user()->id, $subscription);

        return redirect()
            ->route('public.daily.subscribe')
            ->with('success', 'Subscription paused.');
    }

    public function resume(Request $request, DailyContentSubscription $subscription): RedirectResponse
    {
        abort_unless($request->user() !== null, 401);

        app(UnsubscribeDailyContentSubscriptionAction::class)
            ->resumeOwn((int) $request->user()->id, $subscription);

        return redirect()
            ->route('public.daily.subscribe')
            ->with('success', 'Subscription resumed.');
    }
}
