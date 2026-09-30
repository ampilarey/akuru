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
     * @var array<string, array{0: string, 1: 'int'|'bool'|'string'}>
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
        // STATUS §5lq: the important notices by email and by SMS too.
        'notices_email' => ['library.notices.email', 'bool'],
        'notices_sms' => ['library.notices.sms', 'bool'],
        // COMMERCE_PARITY_PLAN P5: the office's own address and number for a sale.
        'office_email' => ['library.notices.office_email', 'string'],
        'office_phone' => ['library.notices.office_phone', 'string'],
    ];

    public function execute(string $key): int|bool|string
    {
        [$configPath, $type] = self::KNOBS[$key] ?? throw new \InvalidArgumentException("Unknown library setting [{$key}].");

        $value = app(GetSettingAction::class)->execute('library.'.$key, config($configPath));

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => trim((string) $value),
            default => (int) $value,
        };
    }

    /**
     * Every knob with its current value and its config default, for the screen.
     *
     * @return array<string, array{value: int|bool|string, default: int|bool|string, type: string}>
     */
    public function all(): array
    {
        $out = [];
        foreach (self::KNOBS as $key => [$configPath, $type]) {
            $default = config($configPath);
            $out[$key] = [
                'value' => $this->execute($key),
                'default' => match ($type) {
                    'bool' => filter_var($default, FILTER_VALIDATE_BOOLEAN),
                    'string' => trim((string) $default),
                    default => (int) $default,
                },
                'type' => $type,
            ];
        }

        return $out;
    }
}
