<?php

namespace App\Support\Services;

use App\Support\Contracts\DocumentRendererInterface;

class HtmlDocumentRenderer implements DocumentRendererInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data): string
    {
        $title = e((string) ($data['title'] ?? $template));
        $body = '';
        foreach ($data as $key => $value) {
            if (is_array($value) || $key === 'title') {
                continue;
            }
            $body .= '<tr><th>'.e((string) $key).'</th><td>'.e((string) $value).'</td></tr>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
            .$title.'</title></head><body dir="auto"><h1>'.$title.'</h1><table>'
            .$body.'</table></body></html>';
    }
}
