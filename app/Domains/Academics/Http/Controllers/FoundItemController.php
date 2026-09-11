<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListFoundItemsAction;
use App\Domains\Academics\Actions\ReturnFoundItemAction;
use App\Domains\Academics\Actions\SaveFoundItemAction;
use App\Domains\Academics\Models\FoundItem;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin (rule 5): authorize via the route group → validate → Action → respond.
 *
 * The staff screen and the family screen are separate methods rather than one
 * screen with a flag, because they answer different questions: staff need the
 * returned items too, families only need what is still on the shelf.
 */
class FoundItemController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
        ];

        return Inertia::render('Academics/FoundItems/Index', [
            'filters' => $filters,
            'items' => app(ListFoundItemsAction::class)->execute($filters),
        ]);
    }

    public function store(Request $request, SaveFoundItemAction $save): RedirectResponse
    {
        $data = $this->validated($request);

        $save->execute($data, (int) $request->user()->id, null, $request->file('photo'));

        return back()->with('success', 'Item logged.');
    }

    public function update(Request $request, FoundItem $foundItem, SaveFoundItemAction $save): RedirectResponse
    {
        $data = $this->validated($request);

        $save->execute($data, (int) $request->user()->id, $foundItem, $request->file('photo'));

        return back()->with('success', 'Item updated.');
    }

    public function return(Request $request, FoundItem $foundItem, ReturnFoundItemAction $return): RedirectResponse
    {
        $data = $request->validate([
            'returned_to' => ['nullable', 'string', 'max:191'],
        ]);

        $return->execute($foundItem, (int) $request->user()->id, $data['returned_to'] ?? null);

        return back()->with('success', 'Marked as returned.');
    }

    /**
     * The photo. Private media, so it is served rather than linked — and only
     * to someone who can already see the list it belongs to.
     */
    public function photo(Request $request, FoundItem $foundItem): HttpResponse
    {
        abort_if($foundItem->photo_media_id === null, 404);

        $media = app(ReadPrivateMediaAction::class)->execute((int) $foundItem->photo_media_id);

        // The row can outlive the file — a cleared disk, a failed restore.
        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($media['original_name']).'"',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $items = app(ListFoundItemsAction::class)->execute([
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
        ]);

        return response()->streamDownload(function () use ($items): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['found_at', 'title', 'description', 'location', 'held_at', 'status', 'returned_at', 'returned_to']);
            foreach ($items as $item) {
                fputcsv($out, [
                    $item['found_at'], $item['title'], $item['description'], $item['location'],
                    $item['held_at'], $item['status'], $item['returned_at'], $item['returned_to'],
                ]);
            }
            fclose($out);
        }, 'lost-and-found.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:191'],
            'held_at' => ['nullable', 'string', 'max:191'],
            'found_at' => ['nullable', 'date'],
            'photo' => ['nullable', 'file', 'image', 'max:8192'],
        ], [], [
            'held_at' => 'where it is held',
            'found_at' => 'date found',
        ]);
    }
}
