<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use App\Support\Contracts\QuranTextProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListDailyContentsAction
{
    /**
     * @param  array{month?: string, status?: string, content_type?: string, theme_tag?: string, q?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $rawMonth = (string) ($filters['month'] ?? '');
        $month = preg_match('/^\d{4}-\d{2}$/', $rawMonth)
            ? Carbon::createFromFormat('Y-m-d', $rawMonth.'-01')->timezone(config('app.timezone'))->startOfMonth()
            : now()->timezone(config('app.timezone'))->startOfMonth();

        $query = DailyContent::query()
            ->whereDate('publish_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('publish_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->orderBy('publish_date')
            ->orderBy('content_type');

        if (! empty($filters['status'])) {
            $status = DailyContentStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }
        if (! empty($filters['content_type'])) {
            $type = DailyContentType::tryFrom((string) $filters['content_type']);
            if ($type !== null) {
                $query->where('content_type', $type);
            }
        }
        if (! empty($filters['theme_tag'])) {
            $query->where('theme_tag', $filters['theme_tag']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('text_en', 'like', $term)
                    ->orWhere('text_dv', 'like', $term)
                    ->orWhere('hadith_text_en', 'like', $term)
                    ->orWhere('attribution', 'like', $term)
                    ->orWhere('hadith_collection', 'like', $term);
            });
        }

        $provider = app(QuranTextProviderInterface::class);

        return $query->get()->map(fn (DailyContent $row) => $this->present($row, $provider))->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalQueue(): array
    {
        $provider = app(QuranTextProviderInterface::class);

        return DailyContent::query()
            ->whereNull('approved_by')
            ->where('status', DailyContentStatus::Draft)
            ->orderBy('publish_date')
            ->get()
            ->map(fn (DailyContent $row) => $this->present($row, $provider))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function previewAyah(int $surahNumber, int $ayahNumber): ?array
    {
        return app(QuranTextProviderInterface::class)->ayahWithMeanings($surahNumber, $ayahNumber);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(DailyContent $row, ?QuranTextProviderInterface $provider = null): array
    {
        $provider ??= app(QuranTextProviderInterface::class);
        $type = $row->content_type instanceof DailyContentType ? $row->content_type->value : (string) $row->content_type;
        $ayah = $row->quran_ayah_id ? $provider->ayahWithMeaningsById((int) $row->quran_ayah_id) : null;
        $date = $row->publish_date?->toDateString();
        $sharePath = $row->share_card_path;
        $shareUrl = $sharePath ? app(MediaStorageInterface::class)->url('public', $sharePath) : null;

        return [
            'id' => $row->id,
            'content_type' => $type,
            'publish_date' => $date,
            'status' => $row->status instanceof DailyContentStatus ? $row->status->value : (string) $row->status,
            'quran_ayah_id' => $row->quran_ayah_id,
            'ayah' => $ayah,
            'hadith_text_ar' => $row->hadith_text_ar,
            'hadith_text_en' => $row->hadith_text_en,
            'hadith_text_dv' => $row->hadith_text_dv,
            'hadith_collection' => $row->hadith_collection,
            'hadith_number' => $row->hadith_number,
            'hadith_grading' => $row->hadith_grading,
            'grading_source' => $row->grading_source,
            'text_en' => $row->text_en,
            'text_dv' => $row->text_dv,
            'text_ar' => $row->text_ar,
            'attribution' => $row->attribution,
            'theme_tag' => $row->theme_tag,
            'notes_internal' => $row->notes_internal,
            'created_by' => $row->created_by,
            'approved_by' => $row->approved_by,
            'share_card_path' => $sharePath,
            'share_card_url' => $shareUrl,
            'permalink_path' => $date ? 'daily/'.$type.'/'.$date : null,
        ];
    }
}
