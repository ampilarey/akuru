<?php

namespace App\Domains\Media\Contracts;

interface ImageProcessorInterface
{
    public function getWebPPath(string $storagePath): ?string;
}
