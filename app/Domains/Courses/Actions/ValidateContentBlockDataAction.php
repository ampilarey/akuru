<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ContentBlockType;
use Illuminate\Validation\ValidationException;

class ValidateContentBlockDataAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $settings
     * @return array{data: array<string, mixed>, settings: array<string, mixed>}
     */
    public function execute(string $type, array $data, array $settings = []): array
    {
        $blockType = ContentBlockType::tryFrom($type);
        if ($blockType === null) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported block type for this slice: '.$type,
            ]);
        }

        $direction = (string) ($settings['direction'] ?? 'auto');
        if (! in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            throw ValidationException::withMessages([
                'settings' => 'Text direction must be ltr, rtl, or auto.',
            ]);
        }

        $clean = match ($blockType) {
            ContentBlockType::Text => [
                'body' => trim((string) ($data['body'] ?? '')),
            ],
            ContentBlockType::RichText => [
                'html' => $this->plainHtml((string) ($data['html'] ?? $data['body'] ?? '')),
            ],
            ContentBlockType::Instruction => [
                'body' => trim((string) ($data['body'] ?? '')),
                'tone' => (string) ($data['tone'] ?? 'note'),
            ],
        };

        if (($clean['body'] ?? $clean['html'] ?? '') === '') {
            throw ValidationException::withMessages([
                'data' => 'Block content is required.',
            ]);
        }

        if ($blockType === ContentBlockType::Instruction && ! in_array($clean['tone'], ['note', 'tip', 'warning'], true)) {
            throw ValidationException::withMessages(['data' => 'Instruction tone must be note, tip, or warning.']);
        }

        return [
            'data' => $clean,
            'settings' => ['direction' => $direction],
        ];
    }

    private function plainHtml(string $html): string
    {
        return trim(strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><a>'));
    }
}
