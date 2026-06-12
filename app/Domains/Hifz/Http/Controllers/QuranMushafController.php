<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\QuranWord;
use App\Domains\Hifz\Services\QuranMushafImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranMushafController extends Controller
{
    public function __construct(protected QuranMushafImportService $importService) {}

    public function index(): View
    {
        $this->authorize('viewAny', QuranMushaf::class);

        $mushafs = QuranMushaf::with('approver')->latest()->paginate(15);

        return view('hifz.quran.mushafs.index', compact('mushafs'));
    }

    public function create(): View
    {
        $this->authorize('manage', QuranMushaf::class);

        return view('hifz.quran.mushafs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', QuranMushaf::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source_file' => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'page_count' => 'nullable|integer|min:1|max:604',
        ]);

        $mushaf = QuranMushaf::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'page_count' => $data['page_count'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('source_file')) {
            $this->importService->storeSourceFile($mushaf, $request->file('source_file'));
        }

        if ($mushaf->page_count) {
            for ($i = 1; $i <= $mushaf->page_count; $i++) {
                $this->importService->createPagePlaceholder($mushaf, $i);
            }
        }

        return redirect()->route('hifz.quran.mushafs.show', $mushaf)
            ->with('success', 'Mushaf created.');
    }

    public function show(QuranMushaf $mushaf): View
    {
        $this->authorize('viewAny', QuranMushaf::class);

        $mushaf->loadCount(['pages', 'ayahs', 'words']);

        return view('hifz.quran.mushafs.show', compact('mushaf'));
    }

    public function approve(QuranMushaf $mushaf): RedirectResponse
    {
        $this->authorize('approve', $mushaf);
        $this->importService->activate($mushaf, auth()->user());

        return back()->with('success', 'Mushaf approved and activated.');
    }

    public function lock(QuranMushaf $mushaf): RedirectResponse
    {
        $this->authorize('lock', $mushaf);
        $this->importService->lock($mushaf);

        return back()->with('success', 'Mushaf locked.');
    }

    public function importAyah(Request $request, QuranMushaf $mushaf): RedirectResponse
    {
        $this->authorize('manage', QuranMushaf::class);

        $data = $request->validate([
            'surah_number' => 'required|integer|min:1|max:114',
            'ayah_number' => 'required|integer|min:1',
            'text_uthmani' => 'required|string',
            'page_number' => 'nullable|integer|min:1',
            'words' => 'nullable|array',
            'words.*' => 'string',
        ]);

        $ayah = QuranAyah::updateOrCreate(
            [
                'quran_mushaf_id' => $mushaf->id,
                'surah_number' => $data['surah_number'],
                'ayah_number' => $data['ayah_number'],
            ],
            [
                'text_uthmani' => $data['text_uthmani'],
                'page_number' => $data['page_number'] ?? null,
            ]
        );

        if (! empty($data['words'])) {
            foreach ($data['words'] as $i => $wordText) {
                QuranWord::updateOrCreate(
                    [
                        'quran_mushaf_id' => $mushaf->id,
                        'surah_number' => $data['surah_number'],
                        'ayah_number' => $data['ayah_number'],
                        'word_number' => $i + 1,
                    ],
                    [
                        'quran_ayah_id' => $ayah->id,
                        'word_text' => $wordText,
                        'page_number' => $data['page_number'] ?? null,
                    ]
                );
            }
        }

        return back()->with('success', 'Ayah imported.');
    }
}
