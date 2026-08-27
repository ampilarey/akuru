<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use App\Support\Contracts\QuranTextProviderInterface;
use Illuminate\Validation\ValidationException;

class SaveDailyContentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?DailyContent $existing, int $actorId): DailyContent
    {
        $type = DailyContentType::tryFrom((string) ($data['content_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['content_type' => 'Choose ayah, hadith, saying, or reminder.']);
        }

        $status = DailyContentStatus::tryFrom((string) ($data['status'] ?? DailyContentStatus::Draft->value))
            ?? DailyContentStatus::Draft;

        if (in_array($status, [DailyContentStatus::Scheduled, DailyContentStatus::Published], true)) {
            throw ValidationException::withMessages([
                'status' => 'Scheduled and published rows must go through scholarly approval.',
            ]);
        }

        $publishDate = (string) ($data['publish_date'] ?? '');
        if ($publishDate === '') {
            throw ValidationException::withMessages(['publish_date' => 'A publish date is required.']);
        }

        $duplicate = DailyContent::query()
            ->whereDate('publish_date', $publishDate)
            ->where('content_type', $type)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'publish_date' => 'That type is already used on this date.',
            ]);
        }

        $payload = [
            'content_type' => $type,
            'publish_date' => $publishDate,
            'theme_tag' => $this->nullableString($data['theme_tag'] ?? null),
            'notes_internal' => $this->nullableString($data['notes_internal'] ?? null),
            'quran_ayah_id' => null,
            'hadith_text_ar' => null,
            'hadith_text_en' => null,
            'hadith_text_dv' => null,
            'hadith_collection' => null,
            'hadith_number' => null,
            'hadith_grading' => null,
            'grading_source' => null,
            'text_en' => null,
            'text_dv' => null,
            'text_ar' => null,
            'attribution' => null,
        ];

        if ($type === DailyContentType::Ayah) {
            $ayah = app(QuranTextProviderInterface::class)->findAyah(
                (int) ($data['surah_number'] ?? 0),
                (int) ($data['ayah_number'] ?? 0),
            );
            if ($ayah === null) {
                throw ValidationException::withMessages([
                    'ayah_number' => 'That ayah is not in the active mushaf.',
                ]);
            }
            $payload['quran_ayah_id'] = (int) $ayah['id'];
        } elseif ($type === DailyContentType::Hadith) {
            $payload['hadith_text_ar'] = $this->nullableString($data['hadith_text_ar'] ?? null);
            $payload['hadith_text_en'] = $this->nullableString($data['hadith_text_en'] ?? null);
            $payload['hadith_text_dv'] = $this->nullableString($data['hadith_text_dv'] ?? null);
            $payload['hadith_collection'] = $this->nullableString($data['hadith_collection'] ?? null);
            $payload['hadith_number'] = $this->nullableString($data['hadith_number'] ?? null);
            $payload['hadith_grading'] = $this->nullableString($data['hadith_grading'] ?? null);
            $payload['grading_source'] = $this->nullableString($data['grading_source'] ?? null);
        } else {
            $payload['text_en'] = $this->nullableString($data['text_en'] ?? null);
            $payload['text_dv'] = $this->nullableString($data['text_dv'] ?? null);
            $payload['text_ar'] = $this->nullableString($data['text_ar'] ?? null);
            $payload['attribution'] = $this->nullableString($data['attribution'] ?? null);
        }

        if ($existing === null) {
            $payload['created_by'] = $actorId;
            $payload['approved_by'] = null;
            $payload['status'] = DailyContentStatus::Draft;
            $row = DailyContent::query()->create($payload);
        } else {
            if ($status === DailyContentStatus::Archived) {
                $payload['status'] = DailyContentStatus::Archived;
            } else {
                $payload['status'] = DailyContentStatus::Draft;
                $payload['approved_by'] = null;
            }
            $existing->fill($payload);
            $existing->save();
            $row = $existing->fresh();
        }

        return $row;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
