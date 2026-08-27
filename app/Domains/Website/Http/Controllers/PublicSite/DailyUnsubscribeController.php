<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Actions\HonorDailyUnsubscribeKeywordAction;
use App\Domains\Website\Actions\UnsubscribeDailyContentSubscriptionAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyUnsubscribeController extends Controller
{
    public function show(string $token)
    {
        $row = app(UnsubscribeDailyContentSubscriptionAction::class)->executeByToken($token);
        abort_unless($row !== null, 404);

        return view('public.daily.unsubscribed', [
            'channel' => $row->channel?->value ?? (string) $row->channel,
        ]);
    }

    public function smsOptOut(Request $request): Response
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'keyword' => ['required', 'string', 'max:64'],
        ]);

        app(HonorDailyUnsubscribeKeywordAction::class)->execute($data['phone'], $data['keyword']);

        return response()->noContent();
    }
}
