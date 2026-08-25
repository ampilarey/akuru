<?php

namespace App\Domains\Media\Jobs;

use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMediaFileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaFileId) {}

    public function handle(ImageProcessorInterface $processor): void
    {
        $file = MediaFile::query()->find($this->mediaFileId);
        if ($file === null) {
            return;
        }

        $file->process_status = 'processing';
        $file->save();

        $meta = is_array($file->meta) ? $file->meta : [];
        if ($file->disk === 'public' && str_starts_with($file->mime, 'image/')) {
            $webp = $processor->getWebPPath($file->path);
            if ($webp !== null) {
                $meta['webp_path'] = $webp;
            }
        }

        $file->meta = $meta;
        $file->process_status = 'processed';
        $file->processed_at = now();
        $file->save();
    }
}
