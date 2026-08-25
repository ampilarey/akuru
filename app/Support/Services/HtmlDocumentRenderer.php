<?php

namespace App\Support\Services;

use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Facades\View;

class HtmlDocumentRenderer implements DocumentRendererInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data): string
    {
        return View::make('documents.'.$template, $data)->render();
    }
}
