<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Jobs\ProcessMediaFileJob;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorePrivateMediaAction
{
    public function __construct(
        private readonly MediaStorageInterface $storage,
        private readonly ImageProcessorInterface $images,
    ) {}

    /**
     * @param  list<string>  $allowedMimes
     * @param  int|null  $shrinkPhotosTo  a JPEG, PNG or WebP photo is scaled down to this
     *                                    many pixels on its long side and re-saved as JPEG
     *                                    (C17 slice R3: identity cards); null keeps it as sent
     * @return array{id: int, mime: string, original_name: string, process_status: string, visibility: string}
     */
    public function execute(UploadedFile $file, ?int $uploadedBy = null, array $allowedMimes = [], ?int $maxBytes = null, ?int $shrinkPhotosTo = null): array
    {
        // `getMimeType()` sniffs the file's contents; `getClientMimeType()` is
        // the browser's claim and is only the fallback. SPEC §30: "Reject
        // uploads by MIME validation/sniffing, not extension only."
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        if ($allowedMimes !== [] && ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'File type '.$mime.' is not allowed for this block.',
            ]);
        }

        // SPEC §30 sets a size limit per kind of media. It is enforced here as
        // well as in the request rules because this Action is the single funnel
        // every private upload passes through, and a caller that forgets a
        // `max:` rule should not be able to store an unbounded file.
        $size = (int) ($file->getSize() ?: 0);
        if ($maxBytes !== null && $size > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'That file is larger than '.(int) round($maxBytes / 1048576).' MB.',
            ]);
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $contents = (string) file_get_contents($file->getRealPath());
        if ($shrinkPhotosTo !== null && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $smaller = $this->images->shrinkForStorage($contents, $shrinkPhotosTo);
            // Kept only when it really is smaller: a photo already small and
            // full of detail can come out larger, and then it stays as sent.
            if ($smaller !== null && strlen($smaller) < strlen($contents)) {
                [$contents, $mime, $extension, $size] = [$smaller, 'image/jpeg', 'jpg', strlen($smaller)];
            }
        }
        $path = 'course-media/'.now()->format('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $this->storage->put('local', $path, $contents);

        $media = MediaFile::query()->create([
            'disk' => 'local',
            'path' => $path,
            'mime' => $mime,
            'original_name' => $file->getClientOriginalName(),
            'size' => $size ?: strlen($contents),
            'uploaded_by' => $uploadedBy,
            'visibility' => 'private',
            'process_status' => 'pending',
        ]);

        ProcessMediaFileJob::dispatch($media->id);

        return [
            'id' => $media->id,
            'mime' => $media->mime,
            'original_name' => $media->original_name,
            'process_status' => $media->process_status,
            'visibility' => $media->visibility,
        ];
    }
}
