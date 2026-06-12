<?php

namespace App\Support\Services;

use App\Support\Contracts\DocumentRendererInterface;

class StubDocumentRenderer implements DocumentRendererInterface
{
    public function render(string $template, array $data): string
    {
        return '';
    }
}
