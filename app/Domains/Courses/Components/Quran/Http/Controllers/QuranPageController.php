<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranPage;
use App\Domains\Courses\Components\Quran\Models\QuranWord;
use App\Domains\Courses\Components\Quran\Models\QuranWordPosition;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'mushaf' => ['id' => $mushaf->id, 'name' => $mushaf->name],
            'page' => [
                'id' => $page->id,
                'page_number' => $page->page_number,
                'image_url' => $page->image_path ? asset('storage/'.$page->image_path) : null,
            ],
            'page_number' => $pageNumber,
            'ayahs' => $ayahs->map(fn (mixed $a) => [
                'id' => $a->id,
                'surah_number' => $a->surah_number,
                'ayah_number' => $a->ayah_number,
                'text_uthmani' => $a->text_uthmani,
            ])->all(),
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
        ]);
    }

    public function storePosition(Request $request, QuranMushaf $mushaf, QuranPage $page): RedirectResponse
    {
        $this->authorize('manage', QuranMushaf::class);

        $data = $request->validate([
            'quran_word_id' => 'required|exists:quran_words,id',
            'x' => 'required|numeric|min:0|max:100',
            'y' => 'required|numeric|min:0|max:100',
            'width' => 'required|numeric|min:0|max:100',
            'height' => 'required|numeric|min:0|max:100',
        ]);

        QuranWordPosition::updateOrCreate(
            [
                'quran_page_id' => $page->id,
                'quran_word_id' => $data['quran_word_id'],
            ],
            [
                'quran_mushaf_id' => $mushaf->id,
                'page_number' => $page->page_number,
                'x' => $data['x'],
                'y' => $data['y'],
                'width' => $data['width'],
                'height' => $data['height'],
                'coordinate_type' => 'percentage',
            ]
        );

        return back()->with('success', 'Word position saved.');
    }
}
