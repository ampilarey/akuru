<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListFoundItemsAction;
use App\Domains\Academics\Actions\ReadListedFoundItemPhotoAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E15, the half that makes it worth building: a family can look.
 *
 * Cross-domain by Action only (rule 3) — Portal never touches the FoundItem
 * model, including for the photo. And only what is still on the shelf: a
 * returned item is the office's record, not a family's shopping list.
 */
class PortalFoundItemController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Portal/FoundItems', [
            'filters' => ['q' => (string) $request->query('q', '')],
            'items' => app(ListFoundItemsAction::class)->execute(
                ['q' => (string) $request->query('q', '')],
                stillHereOnly: true,
            ),
        ]);
    }

    public function photo(int $foundItem, ReadListedFoundItemPhotoAction $read): HttpResponse
    {
        $media = $read->execute($foundItem);

        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($media['original_name']).'"',
        ]);
    }
}
