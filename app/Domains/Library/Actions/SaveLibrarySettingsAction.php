<?php

namespace App\Domains\Library\Actions;

use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * B12 (LIBRARY_PLAN §42): the office writes the Library's commercial knobs.
 * The shape of each value is checked here, not only on the form, because
 * a commission over a hundred or a gift-card floor above its ceiling is
 * wrong whoever typed it.
 */
class SaveLibrarySettingsAction
{
    /**
     * @param  array<string, mixed>  $data  keys of `ResolveLibrarySettingAction::KNOBS`; a missing key is left as it is
     * @return array<string, int|bool> what is now in force
     */
    public function execute(array $data): array
    {
        $resolve = app(ResolveLibrarySettingAction::class);
        $next = [];
        foreach (ResolveLibrarySettingAction::KNOBS as $key => [, $type]) {
            if (! array_key_exists($key, $data)) {
                $next[$key] = $resolve->execute($key);

                continue;
            }
            $next[$key] = $type === 'bool'
                ? filter_var($data[$key], FILTER_VALIDATE_BOOLEAN)
                : (int) $data[$key];
        }

        $errors = [];
        if ($next['refund_window_days'] < 0 || $next['refund_window_days'] > 365) {
            $errors['refund_window_days'] = 'The refund window is a number of days from 0 to 365.';
        }
        if ($next['default_writer_commission'] < 0 || $next['default_writer_commission'] > 100) {
            $errors['default_writer_commission'] = 'The writer\'s share is a percentage from 0 to 100.';
        }
        if ($next['min_payout'] < 0) {
            $errors['min_payout'] = 'The minimum payout cannot be negative.';
        }
        if ($next['gift_card_min'] < 1) {
            $errors['gift_card_min'] = 'The smallest gift card is at least MVR 1.';
        }
        if ($next['gift_card_max'] < $next['gift_card_min']) {
            $errors['gift_card_max'] = 'The largest gift card cannot be smaller than the smallest.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $set = app(SetSettingAction::class);
        foreach (ResolveLibrarySettingAction::KNOBS as $key => [, $type]) {
            if (array_key_exists($key, $data)) {
                $set->execute('library.'.$key, $next[$key], $type === 'bool' ? 'boolean' : 'string', 'library', 'Library: '.str_replace('_', ' ', $key));
            }
        }

        return $next;
    }
}
