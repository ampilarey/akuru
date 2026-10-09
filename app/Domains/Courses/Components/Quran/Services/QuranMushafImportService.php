<?php

namespace App\Domains\Courses\Components\Quran\Services;

use App\Domains\Courses\Components\Quran\Models\QuranAyah;
use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranPage;
use App\Domains\Courses\Components\Quran\Models\QuranWord;
use App\Domains\Courses\Components\Quran\Models\QuranWordPosition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The mushaf editorial workflow's writes (F5). Every one of them refuses a
 * locked mushaf (STATUS §5pt): `lock` was the step after approval, and
 * nothing read it, so a locked mushaf's ayahs, words and boxes could still be
 * changed from its own page.
 */
class QuranMushafImportService
{
    public function storeSourceFile(QuranMushaf $mushaf, UploadedFile $file): QuranMushaf
    {
        $path = $file->store("quran/mushafs/{$mushaf->id}", 'local');
        $hash = hash_file('sha256', $file->getRealPath());

        $mushaf->update([
            'source_file_path' => $path,
            'source_hash' => $hash,
            'source_type' => str_contains($file->getMimeType() ?? '', 'pdf') ? 'pdf' : 'word',
        ]);

        return $mushaf;
    }

    public function createPagePlaceholder(QuranMushaf $mushaf, int $pageNumber, ?string $imagePath = null): QuranPage
    {
        return QuranPage::firstOrCreate(
            ['quran_mushaf_id' => $mushaf->id, 'page_number' => $pageNumber],
            ['image_path' => $imagePath, 'pdf_page_number' => $pageNumber]
        );
    }

    /**
     * F5: takes the approver's id rather than an `Identity\Models\User`, so
     * the engine's Qur'an component does not import another domain's model
     * (rule 3). The column and its meaning are unchanged.
     */
    public function activate(QuranMushaf $mushaf, int $approverId): QuranMushaf
    {
        QuranMushaf::where('id', '!=', $mushaf->id)->update(['is_active' => false]);

        $mushaf->update([
            'is_active' => true,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $mushaf;
    }

    public function lock(QuranMushaf $mushaf): QuranMushaf
    {
        $mushaf->update(['locked' => true]);

        return $mushaf;
    }

    /** A locked mushaf is final: nothing on it changes (STATUS §5pt). */
    public function assertEditable(QuranMushaf $mushaf): void
    {
        if ($mushaf->locked) {
            throw ValidationException::withMessages(['mushaf' => __('teach.error_mushaf_locked')]);
        }
    }

    /**
     * A mushaf's pages, up to `$upTo` (STATUS §5pt).
     *
     * The upload form makes the pages only when it is given a page count, and
     * nothing could add them afterwards, so a mushaf uploaded without one could
     * never be mapped. Pages are only ever added: a page may already carry
     * word boxes.
     */
    public function addPages(QuranMushaf $mushaf, int $upTo): int
    {
        $this->assertEditable($mushaf);
        $have = (int) $mushaf->pages()->count();
        if ($upTo <= $have) {
            throw ValidationException::withMessages(['page_count' => __('teach.error_mushaf_pages_fewer', ['count' => $have])]);
        }
        for ($number = 1; $number <= $upTo; $number++) {
            $this->createPagePlaceholder($mushaf, $number);
        }
        $mushaf->update(['page_count' => $upTo]);

        return $upTo;
    }

    /**
     * A page's image, on the public disk the page view reads it from
     * (`asset('storage/…')`). Nothing set `image_path` before (STATUS §5pt), so
     * every page said it had no image yet. A replaced image's file goes.
     */
    public function storePageImage(QuranPage $page, UploadedFile $image): QuranPage
    {
        $this->assertEditable($page->mushaf);
        $previous = $page->image_path;
        $path = $image->store("quran/pages/{$page->quran_mushaf_id}", 'public');
        [$width, $height] = getimagesize($image->getRealPath()) ?: [null, null];
        $page->update(['image_path' => $path, 'width' => $width, 'height' => $height]);
        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $page;
    }

    /**
     * An ayah and its words, typed in from the mushaf's page. Moved here from
     * the controller with the lock check it needed (STATUS §5pt).
     *
     * @param  array{surah_number: int, ayah_number: int, text_uthmani: string, page_number?: int|null, words?: list<string|null>}  $data
     */
    public function importAyah(QuranMushaf $mushaf, array $data): QuranAyah
    {
        $this->assertEditable($mushaf);
        $ayah = QuranAyah::updateOrCreate(
            ['quran_mushaf_id' => $mushaf->id, 'surah_number' => $data['surah_number'], 'ayah_number' => $data['ayah_number']],
            ['text_uthmani' => $data['text_uthmani'], 'page_number' => $data['page_number'] ?? null],
        );
        $words = array_values(array_filter($data['words'] ?? [], fn (?string $word) => $word !== null && trim($word) !== ''));
        foreach ($words as $i => $wordText) {
            QuranWord::updateOrCreate(
                ['quran_mushaf_id' => $mushaf->id, 'surah_number' => $data['surah_number'], 'ayah_number' => $data['ayah_number'], 'word_number' => $i + 1],
                ['quran_ayah_id' => $ayah->id, 'word_text' => $wordText, 'page_number' => $data['page_number'] ?? null],
            );
        }

        return $ayah;
    }

    /**
     * A word's box on a page, as percentages of the page. The page and the
     * word are the mushaf's own: the route took any page id and any word id,
     * so a box could join one mushaf's word to another's page (STATUS §5pt).
     *
     * @param  array{quran_word_id: int, x: float, y: float, width: float, height: float}  $data
     */
    public function savePosition(QuranMushaf $mushaf, QuranPage $page, array $data): QuranWordPosition
    {
        $this->assertEditable($mushaf);

        return QuranWordPosition::updateOrCreate(
            ['quran_page_id' => $page->id, 'quran_word_id' => $data['quran_word_id']],
            [
                'quran_mushaf_id' => $mushaf->id,
                'page_number' => $page->page_number,
                'x' => $data['x'],
                'y' => $data['y'],
                'width' => $data['width'],
                'height' => $data['height'],
                'coordinate_type' => 'percentage',
            ],
        );
    }
}
