<?php

namespace App\Domains\Media\Contracts;

interface MediaStorageInterface
{
    public function exists(string $disk, string $path): bool;

    public function path(string $disk, string $path): string;

    public function put(string $disk, string $path, string $contents): bool;
}
