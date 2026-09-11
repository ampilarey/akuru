<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\FoundItemStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\FoundItem;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Log something that turned up, or correct an earlier entry.
 *
 * Unlike E13a's materials, **any member of staff may edit any item**. A
 * material carries its author's wording and voice; a lost jumper does not. The
 * person who finds a bag is often not the person who later learns whose it is,
 * and making them chase the original finder to fix the description would be the
 * kind of rule that gets worked around by logging a duplicate.
 */
class SaveFoundItemAction
{
    /** A photograph, and nothing else — this is not a document store. */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $userId, ?FoundItem $item = null, ?UploadedFile $photo = null): FoundItem
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Say what the item is.']);
        }

        $attributes = [
            'title' => $title,
            'description' => $this->nullable($data['description'] ?? null),
            'location' => $this->nullable($data['location'] ?? null),
            'held_at' => $this->nullable($data['held_at'] ?? null),
            'found_at' => $this->foundAt($data['found_at'] ?? null),
        ];

        if ($photo !== null) {
            $stored = app(StorePrivateMediaAction::class)->execute($photo, $userId, self::ALLOWED_MIMES);
            $attributes['photo_media_id'] = $stored['id'] ?? null;
        }

        if ($item !== null) {
            $item->update($attributes);

            return $item->refresh();
        }

        // Stamped, not chosen. Nobody logging a lost water bottle should have
        // to think about which academic year it is (rule 10).
        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');
        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'title' => 'No academic year is active, so there is nothing to file this against.',
            ]);
        }

        return FoundItem::query()->create($attributes + [
            'academic_year_id' => $yearId,
            'logged_by' => $userId,
            'status' => FoundItemStatus::Listed->value,
        ]);
    }

    private function foundAt(mixed $value): string
    {
        $date = trim((string) ($value ?? ''));

        // Defaulting to today is right far more often than it is wrong: things
        // are logged when they are handed in.
        return $date !== '' ? $date : now()->toDateString();
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
