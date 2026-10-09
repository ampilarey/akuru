<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranPage;
use App\Domains\Courses\Components\Quran\Models\QuranWord;
use App\Domains\Courses\Components\Quran\Models\QuranWordPosition;
use App\Domains\Courses\Components\Quran\Services\QuranMushafImportService;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page mapping: which rectangle on a mushaf page is which word. The Blade
 * original fetched the word list through a separate JSON endpoint and reloaded
 * the page after every save; here the words for the page ship with the render,
 * so the mapping form has no second round trip and no `location.reload()`.
 */
class QuranPageController extends Controller
{
    public function show(QuranMushaf $mushaf, int $pageNumber): Response
    {
        $this->authorize('viewAny', QuranMushaf::class);

        $page = $mushaf->pages()->where('page_number', $pageNumber)->firstOrFail();

        $ayahs = $mushaf->ayahs()
            ->where('page_number', $pageNumber)
            ->orderBy('surah_number')
            ->orderBy('ayah_number')
            ->get(['id', 'surah_number', 'ayah_number', 'text_uthmani']);

        $positions = $page->wordPositions()->with('word')->get();

        return Inertia::render('Courses/Quran/Pages/Show', [
            'mushaf' => ['id' => $mushaf->id, 'name' => $mushaf->name, 'locked' => (bool) $mushaf->locked],
            'page' => [
                'id' => $page->id,
                'page_number' => $page->page_number,
                'image_url' => $page->image_path ? asset('storage/'.$page->image_path) : null,
            ],
            'page_number' => $pageNumber,
            // The last page has no next one; the link led to a 404 (slice CT5b).
            'last_page' => (int) $mushaf->pages()->max('page_number'),
            'ayahs' => $ayahs->map->only(['id', 'surah_number', 'ayah_number', 'text_uthmani'])->all(),
            'positions' => $positions->map(fn (QuranWordPosition $p) => [
                'id' => $p->id,
                'x' => (float) $p->x,
                'y' => (float) $p->y,
                'width' => (float) $p->width,
                'height' => (float) $p->height,
                'word_text' => $p->word?->word_text,
            ])->all(),
            'words' => QuranWord::query()
                ->where('quran_mushaf_id', $mushaf->id)
                ->where('page_number', $pageNumber)
                ->orderBy('surah_number')
                ->orderBy('ayah_number')
                ->orderBy('word_number')
                ->get(['id', 'surah_number', 'ayah_number', 'word_number', 'word_text'])
                ->map(fn (QuranWord $w) => [
                    'id' => $w->id,
                    'label' => "{$w->surah_number}:{$w->ayah_number} #{$w->word_number} {$w->word_text}",
                ])
                ->all(),
            'can_manage' => request()->user()?->can('manage', QuranMushaf::class) ?? false,
            't' => Phrases::once('teach'),
        ]);
    }

    public function storePosition(Request $request, QuranMushaf $mushaf, QuranPage $page): RedirectResponse
    {
        $this->authorize('manage', QuranMushaf::class);
        // The page and the word are this mushaf's (STATUS §5pt).
        abort_unless((int) $page->quran_mushaf_id === (int) $mushaf->id, 404);

        $data = $request->validate([
            'quran_word_id' => ['required', Rule::exists('quran_words', 'id')->where('quran_mushaf_id', $mushaf->id)],
            'x' => 'required|numeric|min:0|max:100',
            'y' => 'required|numeric|min:0|max:100',
            'width' => 'required|numeric|min:0|max:100',
            'height' => 'required|numeric|min:0|max:100',
        ]);
        app(QuranMushafImportService::class)->savePosition($mushaf, $page, $data);

        return back()->with('success', __('teach.flash_qpage_position_saved'));
    }

    /** A page's image (STATUS §5pt): nothing could set one before. */
    public function storeImage(Request $request, QuranMushaf $mushaf, QuranPage $page): RedirectResponse
    {
        $this->authorize('manage', QuranMushaf::class);
        abort_unless((int) $page->quran_mushaf_id === (int) $mushaf->id, 404);

        $data = $request->validate(['page_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240']);
        app(QuranMushafImportService::class)->storePageImage($page, $data['page_image']);

        return back()->with('success', __('teach.flash_qpage_image_saved'));
    }
}
