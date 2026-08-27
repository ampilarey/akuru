<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDailyContentBatchAction
{
    /**
     * Theme batch: consecutive reminder drafts (admin curation only).
     *
     * @param  array<string, mixed>  $data
     * @return list<DailyContent>
     */
    public function execute(array $data, int $actorId): array
    {
        $start = (string) ($data['publish_date'] ?? '');
        $days = (int) ($data['days'] ?? 0);
        if ($start === '' || $days < 1 || $days > 40) {
            throw ValidationException::withMessages(['days' => 'Theme batches cover 1–40 consecutive days.']);
        }

        $cursor = Carbon::parse($start)->timezone(config('app.timezone'))->startOfDay();
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $clash = DailyContent::query()
            ->where('content_type', DailyContentType::Reminder)
            ->whereIn('publish_date', $dates)
            ->exists();
        if ($clash) {
            throw ValidationException::withMessages([
                'publish_date' => 'A reminder already exists on one of those dates.',
            ]);
        }

        return DB::transaction(function () use ($data, $actorId, $dates): array {
            $created = [];
            $save = app(SaveDailyContentAction::class);
            foreach ($dates as $date) {
                $created[] = $save->execute([
                    'content_type' => DailyContentType::Reminder->value,
                    'publish_date' => $date,
                    'status' => 'draft',
                    'text_en' => $data['text_en'] ?? null,
                    'text_dv' => $data['text_dv'] ?? null,
                    'text_ar' => $data['text_ar'] ?? null,
                    'attribution' => $data['attribution'] ?? null,
                    'theme_tag' => $data['theme_tag'] ?? null,
                    'notes_internal' => $data['notes_internal'] ?? null,
                ], null, $actorId);
            }

            return $created;
        });
    }
}
