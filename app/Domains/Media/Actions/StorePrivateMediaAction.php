<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Jobs\ProcessMediaFileJob;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorePrivateMediaAction
{
    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @param  list<string>  $allowedMimes
     * @return array{id: int, mime: string, original_name: string, process_status: string, visibility: string}
     */
    public function execute(UploadedFile $file, ?int $uploadedBy = null, array $allowedMimes = [], ?int $maxBytes = null): array
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
        $path = 'course-media/'.now()->format('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $contents = (string) file_get_contents($file->getRealPath());
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
