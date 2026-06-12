<?php

namespace App\Services\Hifz;

use App\Models\QuranMushaf;
use App\Models\QuranPage;
use App\Models\User;
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

    public function activate(QuranMushaf $mushaf, User $approver): QuranMushaf
    {
        QuranMushaf::where('id', '!=', $mushaf->id)->update(['is_active' => false]);

        $mushaf->update([
            'is_active' => true,
            'approved_by' => $approver->id,
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
