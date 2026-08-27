<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ListLibraryCategoriesAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\ListLibraryPurchasesAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryCategoryAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\LibraryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L1 admin (LIBRARY_PLAN §39.1): upload, edit, publish/unpublish,
 * categories. Inertia — a new admin area, so the new-UI rule applies
 * (the public zone stays Blade). library.manage gated.
 */
class AdminLibraryController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $filters = $request->only(['q', 'content_type', 'category', 'tag']);
        $items = app(ListLibraryItemsAction::class)->execute($filters, publishedOnly: false);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($items): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'title', 'type', 'access', 'status', 'category', 'published_at']);
                foreach ($items as $row) {
                    fputcsv($out, [
                        $row['id'],
                        $row['title'],
                        $row['content_type'],
                        $row['access_type'],
                        $row['status'],
                        $row['category']['name'] ?? '',
                        $row['published_at'],
                    ]);
                }
                fclose($out);
            }, 'library-admin.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Library/Admin', [
            'items' => $items,
            'categories' => app(ListLibraryCategoriesAction::class)->execute(activeOnly: false, withCounts: true),
            'sales' => app(ListLibraryPurchasesAction::class)->salesSummary(),
            'filters' => $filters,
            'options' => [
                'content_types' => array_map(fn ($case) => $case->value, LibraryContentType::cases()),
                'access_types' => array_map(fn ($case) => $case->value, LibraryAccessType::cases()),
            ],
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $data = $this->validatedItem($request);

        app(SaveLibraryItemAction::class)->execute(
            $data + ['created_by' => (int) $request->user()->id],
            null,
            $request->file('pdf'),
        );

        return back()->with('success', 'Library item saved.');
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $model = LibraryItem::query()->findOrFail($item);
        $data = $this->validatedItem($request);

        app(SaveLibraryItemAction::class)->execute($data, $model, $request->file('pdf'));

        return back()->with('success', 'Library item updated.');
    }

    public function publish(Request $request, int $item): RedirectResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $data = $request->validate(['publish' => 'required|boolean']);

        app(PublishLibraryItemAction::class)->execute(
            $item,
            (int) $request->user()->id,
            (bool) $data['publish'],
        );

        return back()->with('success', 'Library item status updated.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_dv' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        app(SaveLibraryCategoryAction::class)->execute($data);

        return back()->with('success', 'Category saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'abstract' => 'nullable|string|max:5000',
            'content_type' => 'required|string|max:30',
            'access_type' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:5',
            'library_category_id' => 'nullable|integer',
            'cover_image' => 'nullable|string|max:2048',
            'body' => 'nullable|string',
            'page_count' => 'nullable|integer|min:1',
            'reading_time' => 'nullable|integer|min:1',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'authors' => 'nullable|array',
            'authors.*.name' => 'required_with:authors|string|max:255',
            'authors.*.user_id' => 'nullable|integer',
            'pdf' => 'nullable|file|mimes:pdf|max:51200',
        ]);
    }
}
