<?php

namespace App\Domains\Courses\Components\Quran\Services;

use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranPage;
use Illuminate\Http\UploadedFile;

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
}
