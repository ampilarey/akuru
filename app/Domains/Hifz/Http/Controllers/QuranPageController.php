<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\QuranPage;
use App\Domains\Hifz\Models\QuranWord;
use App\Domains\Hifz\Models\QuranWordPosition;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranPageController extends Controller
{
    public function show(QuranMushaf $mushaf, int $pageNumber): View
    {
        $this->authorize('viewAny', QuranMushaf::class);

        $page = $mushaf->pages()->where('page_number', $pageNumber)->firstOrFail();
        $ayahs = $mushaf->ayahs()->where('page_number', $pageNumber)->orderBy('surah_number')->orderBy('ayah_number')->get();
        $positions = $page->wordPositions()->with('word')->get();

        return view('hifz.quran.pages.show', compact('mushaf', 'page', 'ayahs', 'positions', 'pageNumber'));
    }

    public function words(Request $request, QuranMushaf $mushaf): JsonResponse
    {
        $request->validate([
            'surah_number' => 'required|integer',
            'ayah_number' => 'required|integer',
        ]);

        $words = QuranWord::where('quran_mushaf_id', $mushaf->id)
            ->where('surah_number', $request->surah_number)
            ->where('ayah_number', $request->ayah_number)
            ->orderBy('word_number')
            ->get();

        return response()->json($words);
    }

    public function storePosition(Request $request, QuranMushaf $mushaf, QuranPage $page): RedirectResponse|JsonResponse
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

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Word position saved.');
    }
}
