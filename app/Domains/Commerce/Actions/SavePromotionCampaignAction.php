<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Enums\DiscountType;
use App\Domains\Commerce\Models\PromotionCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * B4 (§18): the office starts a campaign — a name readers see, a window,
 * a discount, who funds it, and what it covers. Targets are strings and
 * ids; the caller (the Library office) knows what they mean, Commerce only
 * keeps them. A campaign is never edited once started: end it and start
 * another, so a redemption's terms stay what they were.
 */
class SavePromotionCampaignAction
{
    /**
     * `all` is everything paid in the library; `gift_card` (B4b) is a bonus
     * on gift cards bought while the campaign runs — the one target that is
     * not a price reduction, so `all` never reaches it.
     *
     * @var list<string>
     */
    public const TARGET_TYPES = ['all', 'library_item', 'library_category', 'writer_profile', 'gift_card'];

    /** @var list<string> */
    public const FUNDING_SOURCES = ['shared', 'akuru', 'writer'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $createdBy = null): PromotionCampaign
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Give the campaign a name readers will see.']);
        }
        $type = DiscountType::tryFrom((string) ($data['discount_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['discount_type' => 'A campaign takes a percentage or a fixed amount off.']);
        }
        $value = (float) ($data['discount_value'] ?? 0);
        if ($value <= 0 || ($type === DiscountType::Percentage && $value > 100)) {
            throw ValidationException::withMessages(['discount_value' => 'Invalid discount value.']);
        }
        $starts = ! empty($data['starts_at']) ? Carbon::parse((string) $data['starts_at']) : now();
        $ends = ! empty($data['ends_at']) ? Carbon::parse((string) $data['ends_at']) : null;
        if ($ends !== null && $ends->lte($starts)) {
            throw ValidationException::withMessages(['ends_at' => 'The campaign must end after it starts.']);
        }
        $funding = (string) ($data['funding_source'] ?? 'akuru');
        if (! in_array($funding, self::FUNDING_SOURCES, true)) {
            throw ValidationException::withMessages(['funding_source' => 'Who funds the discount: shared, akuru or writer.']);
        }
        $targets = $this->targets((array) ($data['targets'] ?? []));

        return DB::transaction(function () use ($name, $data, $starts, $ends, $type, $value, $funding, $targets, $createdBy) {
            $campaign = PromotionCampaign::query()->create([
                'name' => $name,
                'slug' => $this->slugFor($name),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'discount_type' => $type,
                'discount_value' => $value,
                'max_discount_amount' => is_numeric($data['max_discount_amount'] ?? null) && (float) $data['max_discount_amount'] > 0 ? (float) $data['max_discount_amount'] : null,
                // B4b: "buy 500" — the order amount from which the offer applies.
                'minimum_amount' => is_numeric($data['minimum_amount'] ?? null) && (float) $data['minimum_amount'] > 0 ? (float) $data['minimum_amount'] : null,
                'funding_source' => $funding,
                'status' => 'active',
                'created_by' => $createdBy,
            ]);
            foreach ($targets as $target) {
                $campaign->targets()->create($target);
            }

            return $campaign->load('targets');
        });
    }

    /**
     * Known types only, each with an id except `all`; no targets means all.
     *
     * @param  list<array<string, mixed>>  $given
     * @return list<array{target_type: string, target_id: int|null}>
     */
    private function targets(array $given): array
    {
        $kept = [];
        foreach ($given as $target) {
            $type = (string) ($target['type'] ?? '');
            if (! in_array($type, self::TARGET_TYPES, true)) {
                throw ValidationException::withMessages(['targets' => 'Unknown target "'.$type.'".']);
            }
            if ($type === 'all') {
                return [['target_type' => 'all', 'target_id' => null]];
            }
            if ($type === 'gift_card') {
                $kept['gift_card'] = ['target_type' => 'gift_card', 'target_id' => null];

                continue;
            }
            if (! is_numeric($target['id'] ?? null)) {
                throw ValidationException::withMessages(['targets' => 'Each '.$type.' target needs an id.']);
            }
            $kept[$type.':'.(int) $target['id']] = ['target_type' => $type, 'target_id' => (int) $target['id']];
        }

        return $kept === [] ? [['target_type' => 'all', 'target_id' => null]] : array_values($kept);
    }

    private function slugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'offer', 60, '');
        $slug = $base;
        while (PromotionCampaign::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
