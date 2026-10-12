<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintImage;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** NAME_TAGS_PLAN NT2: Akuru's artwork for templates, as public media the designer places. */
class UploadPrintImagesAction
{
    public const MAX = 120;

    /**
     * @param  list<UploadedFile>  $files
     * @return list<int> the library ids made
     */
    public function execute(array $files, ?string $alt, int $userId): array
    {
        if (PrintImage::query()->count() + count($files) > self::MAX) {
            throw ValidationException::withMessages(['images' => __('print.error_too_many_images', ['max' => self::MAX])]);
        }
        $ids = [];
        $order = (int) PrintImage::query()->max('sort_order');
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $stored = app(StorePublicMediaAction::class)->execute($file, $userId, (array) config('bookshop.storefront.images.mimes'), ['print_library' => true], 'print-images');
            $ids[] = PrintImage::query()->create([
                'media_file_id' => (int) $stored['id'],
                'alt' => is_string($alt) && trim($alt) !== '' ? mb_substr(trim($alt), 0, 200) : null,
                'sort_order' => ++$order,
                'uploaded_by' => $userId,
            ])->id;
        }

        return $ids;
    }
}
