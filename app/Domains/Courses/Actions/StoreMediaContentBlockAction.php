<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StoreMediaContentBlockAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ContentBlock
    {
        $blockType = ContentBlockType::tryFrom((string) ($data['type'] ?? ''));
        if ($blockType === null || ! $blockType->isMedia()) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported media block type.',
            ]);
        }

        $embedUrl = trim((string) ($data['embed_url'] ?? ''));
        if ($blockType === ContentBlockType::Video && $embedUrl !== '') {
            return app(SaveContentBlockAction::class)->execute([
                'lesson_id' => $data['lesson_id'],
                'type' => $blockType->value,
                'title' => $data['title'] ?? null,
                'data' => ['embed_url' => $embedUrl],
                'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
                'created_by' => $data['created_by'] ?? null,
            ]);
        }

        $file = $data['file'] ?? null;
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'A file is required for this block type.',
            ]);
        }

        $stored = app(StorePrivateMediaAction::class)->execute(
            $file,
            isset($data['created_by']) ? (int) $data['created_by'] : null,
            $blockType->allowedMimes(),
        );

        return app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $data['lesson_id'],
            'type' => $blockType->value,
            'title' => $data['title'] ?? null,
            'data' => [
                'media_id' => $stored['id'],
                'mime' => $stored['mime'],
                'original_name' => $stored['original_name'],
            ],
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
