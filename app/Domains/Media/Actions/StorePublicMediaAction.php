<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorePublicMediaAction
{
    /**
     * @var list<string>
     */
    private const DEFAULT_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
        'image/gif',
    ];

    public function __construct(private readonly MediaStorageInterface $storage) {}

    /**
     * @param  list<string>  $allowedMimes
     * @param  array<string, mixed>  $meta
     * @return array{id: int, url: string, mime: string, original_name: string, visibility: string}
     */
    public function execute(UploadedFile $file, ?int $uploadedBy = null, array $allowedMimes = [], array $meta = []): array
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $allowed = $allowedMimes === [] ? self::DEFAULT_MIMES : $allowedMimes;
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'File type '.$mime.' is not allowed for public media.',
            ]);
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $path = 'trust-logos/'.now()->format('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $contents = (string) file_get_contents($file->getRealPath());
        $this->storage->put('public', $path, $contents);

        $media = MediaFile::query()->create([
            'disk' => 'public',
            'path' => $path,
            'mime' => $mime,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize() ?: strlen($contents),
            'uploaded_by' => $uploadedBy,
            'visibility' => 'public',
            'process_status' => 'processed',
            'processed_at' => now(),
            'meta' => $meta,
        ]);

        return [
            'id' => $media->id,
            'url' => $this->storage->url('public', $path),
            'mime' => $media->mime,
            'original_name' => $media->original_name,
            'visibility' => $media->visibility,
        ];
    }
}
