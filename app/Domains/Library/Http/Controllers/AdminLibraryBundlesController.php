<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ListLibraryBundlesAction;
use App\Domains\Library\Actions\ListPromotionTargetOptionsAction;
use App\Domains\Library\Actions\SaveLibraryBundleAction;
use App\Domains\Library\Models\LibraryBundle;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LIBRARY_PLAN §19 (B4d, STATUS §5un): the office's bundles — make one,
 * change it, switch it on or off, see what it sold. Gated with the Library
 * office (`role:super_admin` + `library.manage`, the route group).
 */
class AdminLibraryBundlesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Library/Bundles', [
            'bundles' => app(ListLibraryBundlesAction::class)->forOffice(),
            'items' => app(ListPromotionTargetOptionsAction::class)->execute()['items'],
            'limits' => ['min' => SaveLibraryBundleAction::MIN_ITEMS, 'max' => SaveLibraryBundleAction::MAX_ITEMS],
            't' => Phrases::once('admin'),
        ]);
    }

    public function store(Request $request, ?int $bundle = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:160', 'title_dv' => 'nullable|string|max:160', 'title_ar' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:2000', 'price' => 'required|numeric|min:0.01|max:100000',
            'item_ids' => 'required|array|max:'.SaveLibraryBundleAction::MAX_ITEMS, 'item_ids.*' => 'integer',
            'is_active' => 'nullable|boolean', 'ends_at' => 'nullable|date',
        ]);
        app(SaveLibraryBundleAction::class)->execute($data, $bundle === null ? null : LibraryBundle::query()->findOrFail($bundle), (int) $request->user()->id);

        return back()->with('success', __('admin.library_bundles_saved'));
    }

    public function export(): StreamedResponse
    {
        $rows = app(ListLibraryBundlesAction::class)->forOffice();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['title', 'slug', 'price', 'list_total', 'currency', 'works', 'on_sale', 'ends', 'sold', 'revenue']);
            foreach ($rows as $b) {
                Csv::put($out, [$b['title'], $b['slug'], $b['price'], $b['list_total'], $b['currency'], implode('; ', array_column($b['items'], 'title')), $b['on_sale'] ? 'yes' : 'no', $b['ends_at'], $b['sold'], $b['revenue']]);
            }
            fclose($out);
        }, 'library-bundles.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
