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
            ContentBlockType::Pdf,
            ContentBlockType::Download => $this->mediaPayload($blockType, $data),
            ContentBlockType::Video => $this->videoPayload($data),
            ContentBlockType::Glossary,
            ContentBlockType::Term => $this->glossaryPayload($data),
            ContentBlockType::Dialogue => $this->dialoguePayload($data),
            ContentBlockType::Flashcard => $this->flashcardPayload($data),
            ContentBlockType::QuizEmbed => $this->embedPayload($data, 'quiz_id'),
            ContentBlockType::AssignmentEmbed => $this->embedPayload($data, 'assignment_id'),
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
     * @return array{entries: list<array{term: string, definition: string}>}
     */
    private function glossaryPayload(array $data): array
    {
        $entries = [];
        $raw = $data['entries'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $term = trim((string) ($row['term'] ?? ''));
                $definition = trim((string) ($row['definition'] ?? ''));
                if ($term !== '' && $definition !== '') {
                    $entries[] = ['term' => $term, 'definition' => $definition];
                }
            }
        }

        $term = trim((string) ($data['term'] ?? ''));
        $definition = trim((string) ($data['definition'] ?? ''));
        if ($term !== '' && $definition !== '') {
            $entries[] = ['term' => $term, 'definition' => $definition];
        }

        if ($entries === []) {
            throw ValidationException::withMessages([
                'data' => 'Glossary and term blocks need at least one term and definition.',
            ]);
        }

        return ['entries' => $entries];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: list<array{speaker: string, text: string}>}
     */
    private function dialoguePayload(array $data): array
    {
        $lines = [];
        foreach (is_array($data['lines'] ?? null) ? $data['lines'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $speaker = trim((string) ($row['speaker'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($speaker !== '' && $text !== '') {
                $lines[] = ['speaker' => $speaker, 'text' => $text];
            }
        }
        if ($lines === []) {
            throw ValidationException::withMessages([
                'data' => 'Dialogue blocks need at least one speaker line.',
            ]);
        }

        return ['lines' => $lines];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{cards: list<array{front: string, back: string}>}
     */
    private function flashcardPayload(array $data): array
    {
        $cards = [];
        foreach (is_array($data['cards'] ?? null) ? $data['cards'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $front = trim((string) ($row['front'] ?? ''));
            $back = trim((string) ($row['back'] ?? ''));
            if ($front !== '' && $back !== '') {
                $cards[] = ['front' => $front, 'back' => $back];
            }
        }
        if ($cards === []) {
            throw ValidationException::withMessages([
                'data' => 'Flashcard blocks need at least one front and back.',
            ]);
        }

        return ['cards' => $cards];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function embedPayload(array $data, string $idKey): array
    {
        $id = isset($data[$idKey]) && $data[$idKey] !== '' ? (int) $data[$idKey] : 0;
        $url = trim((string) ($data['url'] ?? $data['embed_url'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        if ($id < 1 && $url === '') {
            throw ValidationException::withMessages([
                'data' => 'Embed blocks need an id or a URL. Quiz and assignment engines are not built in this slice.',
            ]);
        }
        if ($url !== '') {
            $parts = parse_url($url);
            if (($parts['scheme'] ?? '') !== 'https') {
                throw ValidationException::withMessages([
                    'data' => 'Embed URLs must use https.',
                ]);
            }
        }

        $payload = [];
        if ($id > 0) {
            $payload[$idKey] = $id;
        }
        if ($url !== '') {
            $payload['url'] = $url;
        }
        if ($title !== '') {
            $payload['title'] = $title;
        }

        return $payload;
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
