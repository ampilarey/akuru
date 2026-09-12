<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Models\QuranAyah;
use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranWord;
use App\Domains\Courses\Components\Quran\Services\QuranMushafImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mushaf editorial workflow — the fourth legacy Hifz workflow, which ADR-025's
 * parity list did not name. It moved here with the dataset (F5) rather than
 * being deleted: nothing on the engine side could upload a mushaf, import an
 * ayah or approve one, so deleting it would have removed the only browser path
 * to the data every Qur'an screen reads.
 *
 * Authorization is unchanged from the Blade original — `QuranMushafPolicy`,
 * which is stricter than a role gate (`manage_quran_mushaf` **and** Hifz dean).
 */
class QuranMushafController extends Controller
{
    public function __construct(protected QuranMushafImportService $importService) {}

    public function index(): Response
    {
        $this->authorize('viewAny', QuranMushaf::class);

        return Inertia::render('Courses/Quran/Mushafs/Index', [
            'mushafs' => QuranMushaf::query()
                ->withCount('pages')
                ->latest()
                ->get()
                ->map(fn (QuranMushaf $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'page_count' => $m->page_count ?? $m->pages_count,
                    'is_active' => (bool) $m->is_active,
                    'locked' => (bool) $m->locked,
                ])
                ->all(),
            'can_manage' => request()->user()?->can('manage', QuranMushaf::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage', QuranMushaf::class);

        return Inertia::render('Courses/Quran/Mushafs/Create');
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

        return redirect()->route('quran.mushafs.show', $mushaf)
            ->with('success', 'Mushaf created.');
    }

    public function show(QuranMushaf $mushaf): Response
    {
        $this->authorize('viewAny', QuranMushaf::class);

        $mushaf->loadCount(['pages', 'ayahs', 'words']);
        $user = request()->user();

        return Inertia::render('Courses/Quran/Mushafs/Show', [
            'mushaf' => [
                'id' => $mushaf->id,
                'name' => $mushaf->name,
                'description' => $mushaf->description,
                'source_hash' => $mushaf->source_hash,
                'pages_count' => $mushaf->pages_count,
                'ayahs_count' => $mushaf->ayahs_count,
                'words_count' => $mushaf->words_count,
                'is_active' => (bool) $mushaf->is_active,
                'locked' => (bool) $mushaf->locked,
            ],
            'can_manage' => $user?->can('manage', QuranMushaf::class) ?? false,
            'can_approve' => $user?->can('approve', $mushaf) ?? false,
            'can_lock' => $user?->can('lock', $mushaf) ?? false,
        ]);
    }

    public function approve(QuranMushaf $mushaf): RedirectResponse
    {
        $this->authorize('approve', $mushaf);
        $this->importService->activate($mushaf, (int) auth()->id());

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
            'words.*' => 'nullable|string',
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

        $words = array_values(array_filter(
            $data['words'] ?? [],
            fn (?string $word) => $word !== null && trim($word) !== '',
        ));

        foreach ($words as $i => $wordText) {
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

        return back()->with('success', 'Ayah imported.');
    }
}
