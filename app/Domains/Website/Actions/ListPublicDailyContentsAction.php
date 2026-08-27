<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ListPublicDailyContentsAction
{
    /**
     * @param  array{month?: string, theme_tag?: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function execute(DailyContentType $type, array $filters = []): LengthAwarePaginator
    {
        $query = DailyContent::query()
            ->where('content_type', $type)
            ->where('status', DailyContentStatus::Published)
            ->orderByDesc('publish_date');

        $rawMonth = (string) ($filters['month'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $month = Carbon::createFromFormat('Y-m-d', $rawMonth.'-01')->timezone(config('app.timezone'))->startOfMonth();
            $query->whereDate('publish_date', '>=', $month->copy()->startOfMonth()->toDateString())
                ->whereDate('publish_date', '<=', $month->copy()->endOfMonth()->toDateString());
        }

        if (! empty($filters['theme_tag'])) {
            $query->where('theme_tag', (string) $filters['theme_tag']);
        }

        $lister = app(ListDailyContentsAction::class);

        return $query->paginate(20)->through(fn (DailyContent $row) => $lister->present($row));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublished(DailyContentType $type, string $date): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $row = DailyContent::query()
            ->where('content_type', $type)
            ->whereDate('publish_date', $date)
            ->where('status', DailyContentStatus::Published)
            ->first();

        return $row ? app(ListDailyContentsAction::class)->present($row) : null;
    }

    public function findPublishedRow(DailyContentType $type, string $date): ?DailyContent
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return DailyContent::query()
            ->where('content_type', $type)
            ->whereDate('publish_date', $date)
            ->where('status', DailyContentStatus::Published)
            ->first();
    }

    /**
     * Today's published items only — no fallback to older rows.
     *
     * @param  list<string>  $types
     * @return list<array<string, mixed>>
     */
    public function publishedOnDate(string $date, array $types): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $types === []) {
            return [];
        }

        $allowed = [];
        foreach ($types as $type) {
            $enum = DailyContentType::tryFrom($type);
            if ($enum !== null) {
                $allowed[] = $enum->value;
            }
        }
        if ($allowed === []) {
            return [];
        }

        $order = ['ayah' => 0, 'hadith' => 1, 'saying' => 2, 'reminder' => 3];
        $lister = app(ListDailyContentsAction::class);

        return DailyContent::query()
            ->whereDate('publish_date', $date)
            ->where('status', DailyContentStatus::Published)
            ->whereIn('content_type', $allowed)
            ->get()
            ->sortBy(function (DailyContent $row) use ($order) {
                $type = $row->content_type instanceof DailyContentType
                    ? $row->content_type->value
                    : (string) $row->content_type;

                return $order[$type] ?? 9;
            })
            ->map(fn (DailyContent $row) => $lister->present($row))
            ->values()
            ->all();
    }
}
