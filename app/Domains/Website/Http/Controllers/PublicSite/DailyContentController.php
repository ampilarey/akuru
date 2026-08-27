<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Actions\ComposeDailyContentSeoAction;
use App\Domains\Website\Actions\ListPublicDailyContentsAction;
use App\Domains\Website\Actions\StreamDailyShareCardAction;
use App\Domains\Website\Enums\DailyContentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyContentController extends Controller
{
    public function index(Request $request, string $type)
    {
        $enum = DailyContentType::tryFrom($type);
        abort_unless($enum !== null, 404);

        $filters = $request->only(['month', 'theme_tag']);
        $items = app(ListPublicDailyContentsAction::class)->execute($enum, $filters);

        return view('public.daily.index', [
            'type' => $enum->value,
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function show(string $type, string $date)
    {
        $enum = DailyContentType::tryFrom($type);
        abort_unless($enum !== null, 404);

        $item = app(ListPublicDailyContentsAction::class)->findPublished($enum, $date);
        abort_unless($item !== null, 404);

        $canonical = url()->current();
        $seo = app(ComposeDailyContentSeoAction::class)->execute($item, $canonical);

        return view('public.daily.show', [
            'item' => $item,
            'seo' => $seo,
        ]);
    }

    public function card(string $type, string $date): Response
    {
        $enum = DailyContentType::tryFrom($type);
        abort_unless($enum !== null, 404);

        $row = app(ListPublicDailyContentsAction::class)->findPublishedRow($enum, $date);
        abort_unless($row !== null, 404);

        $png = app(StreamDailyShareCardAction::class)->execute($row);
        abort_unless($png !== null && $png !== '', 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
