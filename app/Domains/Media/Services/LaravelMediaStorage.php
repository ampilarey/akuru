<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Contracts\MediaStorageInterface;
use Illuminate\Support\Facades\Storage;

class LaravelMediaStorage implements MediaStorageInterface
{
    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    public function path(string $disk, string $path): string
    {
        return Storage::disk($disk)->path($path);
    }

    public function put(string $disk, string $path, string $contents): bool
    {
        return Storage::disk($disk)->put($path, $contents);
    }
}
