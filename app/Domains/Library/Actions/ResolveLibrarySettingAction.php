<?php

namespace App\Domains\Library\Actions;

use App\Domains\Settings\Actions\GetSettingAction;

/**
 * B12 (LIBRARY_PLAN §42, STATUS §5ip): the Library's commercial knobs, as
 * the office set them on `/admin/library/settings`, falling back to
 * `config/library.php` (and so to `.env`) where nothing has been set.
 *
 * One door for every reader of a knob, so a value the office typed and a
 * value the deploy carried can never disagree in two places (rule 11). The
 * reading-abuse thresholds stay in config on purpose: they are guesses to
 * be corrected from watching real readers, not office policy.
 */
class ResolveLibrarySettingAction
{
    /**
     * Key → [config path, type]. The type says how a stored string reads back.
     *
     * @var array<string, array{0: string, 1: 'int'|'bool'}>
     */
    public const KNOBS = [
        'refund_window_days' => ['library.refund_window_days', 'int'],
        'default_writer_commission' => ['library.default_writer_commission', 'int'],
        'min_payout' => ['library.min_payout', 'int'],
        'gift_card_min' => ['library.gift_cards.min', 'int'],
        'gift_card_max' => ['library.gift_cards.max', 'int'],
        'gift_card_expiry_months' => ['library.gift_cards.expiry_months', 'int'],
        // R3: accepts a research item needs; replaced the on/off switch.
        'research_reviews_required' => ['library.research_reviews_required', 'int'],
        'payouts_enabled' => ['library.payouts_enabled', 'bool'],
    ];

    public function execute(string $key): int|bool
    {
        [$configPath, $type] = self::KNOBS[$key] ?? throw new \InvalidArgumentException("Unknown library setting [{$key}].");

        $value = app(GetSettingAction::class)->execute('library.'.$key, config($configPath));

        return $type === 'bool'
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
            : (int) $value;
    }

    /**
     * Every knob with its current value and its config default, for the screen.
     *
     * @return array<string, array{value: int|bool, default: int|bool, type: string}>
     */
    public function all(): array
    {
        $out = [];
        foreach (self::KNOBS as $key => [$configPath, $type]) {
            $default = config($configPath);
            $out[$key] = [
                'value' => $this->execute($key),
                'default' => $type === 'bool' ? filter_var($default, FILTER_VALIDATE_BOOLEAN) : (int) $default,
                'type' => $type,
            ];
        }

        return $out;
    }
}
