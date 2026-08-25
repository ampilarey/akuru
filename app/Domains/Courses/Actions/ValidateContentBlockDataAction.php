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
            ContentBlockType::Image,
            ContentBlockType::Audio,
            ContentBlockType::Pdf => $this->mediaPayload($blockType, $data),
            ContentBlockType::Video => $this->videoPayload($data),
        };

        if (in_array($blockType, [ContentBlockType::Text, ContentBlockType::RichText], true)
            && ($clean['body'] ?? $clean['html'] ?? '') === '') {
            throw ValidationException::withMessages([
                'data' => 'Block content is required.',
            ]);
        }

        if ($blockType === ContentBlockType::Instruction) {
            if (($clean['body'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'data' => 'Block content is required.',
                ]);
            }
            if (! in_array($clean['tone'], ['note', 'tip', 'warning'], true)) {
                throw ValidationException::withMessages(['data' => 'Instruction tone must be note, tip, or warning.']);
            }
        }

        return [
            'data' => $clean,
            'settings' => ['direction' => $direction],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{media_id: int, mime: string, original_name: string}
     */
    private function mediaPayload(ContentBlockType $blockType, array $data): array
    {
        $mediaId = (int) ($data['media_id'] ?? 0);
        $mime = (string) ($data['mime'] ?? '');
        $originalName = trim((string) ($data['original_name'] ?? ''));

        if ($mediaId < 1 || $mime === '' || $originalName === '') {
            throw ValidationException::withMessages([
                'data' => 'Media blocks require media_id, mime, and original_name.',
            ]);
        }

        if (! in_array($mime, $blockType->allowedMimes(), true)) {
            throw ValidationException::withMessages([
                'data' => 'MIME type '.$mime.' is not valid for '.$blockType->value.' blocks.',
            ]);
        }

        return [
            'media_id' => $mediaId,
            'mime' => $mime,
            'original_name' => $originalName,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function videoPayload(array $data): array
    {
        $embedUrl = trim((string) ($data['embed_url'] ?? ''));
        if ($embedUrl !== '') {
            return ['embed_url' => $this->normalizeEmbedUrl($embedUrl)];
        }

        return $this->mediaPayload(ContentBlockType::Video, $data);
    }

    private function normalizeEmbedUrl(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            throw ValidationException::withMessages([
                'data' => 'Video embeds must use https.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
            'vimeo.com',
            'www.vimeo.com',
            'player.vimeo.com',
        ];
        if (! in_array($host, $allowed, true)) {
            throw ValidationException::withMessages([
                'data' => 'Only YouTube or Vimeo embeds are allowed.',
            ]);
        }

        if (in_array($host, ['youtu.be'], true)) {
            $id = trim((string) ($parts['path'] ?? ''), '/');

            return $id !== '' ? 'https://www.youtube.com/embed/'.$id : $url;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str($parts['query'] ?? '', $query);
            if (! empty($query['v'])) {
                return 'https://www.youtube.com/embed/'.$query['v'];
            }
            if (str_starts_with((string) ($parts['path'] ?? ''), '/embed/')) {
                return 'https://www.youtube.com'.($parts['path'] ?? '');
            }
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $id = trim((string) ($parts['path'] ?? ''), '/');
            if (ctype_digit($id)) {
                return 'https://player.vimeo.com/video/'.$id;
            }
        }

        return $url;
    }

    private function plainHtml(string $html): string
    {
        return trim(strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><a>'));
    }
}
