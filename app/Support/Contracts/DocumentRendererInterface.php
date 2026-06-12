<?php

namespace App\Support\Contracts;

interface DocumentRendererInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data): string;
}
