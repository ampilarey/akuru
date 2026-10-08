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
     * @return array<string, int|bool|string> what is now in force
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
            $next[$key] = match ($type) {
                'bool' => filter_var($data[$key], FILTER_VALIDATE_BOOLEAN),
                'string' => trim((string) $data[$key]),
                default => (int) $data[$key],
            };
        }

        $errors = [];
        if ($next['refund_window_days'] < 0 || $next['refund_window_days'] > 365) {
            $errors['refund_window_days'] = __('admin.library_settings_error_refund_window');
        }
        if ($next['default_writer_commission'] < 0 || $next['default_writer_commission'] > 100) {
            $errors['default_writer_commission'] = __('admin.library_settings_error_commission');
        }
        if ($next['min_payout'] < 0) {
            $errors['min_payout'] = __('admin.library_settings_error_min_payout');
        }
        if ($next['gift_card_min'] < 1) {
            $errors['gift_card_min'] = __('admin.library_settings_error_gift_min');
        }
        if ($next['gift_card_max'] < $next['gift_card_min']) {
            $errors['gift_card_max'] = __('admin.library_settings_error_gift_max');
        }
        if ($next['gift_card_expiry_months'] < 0 || $next['gift_card_expiry_months'] > 120) {
            $errors['gift_card_expiry_months'] = __('admin.library_settings_error_gift_expiry');
        }
        // R3 (D2): peer review cannot be switched off by asking for none.
        if ($next['research_reviews_required'] < 1 || $next['research_reviews_required'] > 10) {
            $errors['research_reviews_required'] = __('admin.library_settings_error_reviews');
        }
        // P5: the office's own copy of a sale notice.
        if ($next['office_email'] !== '' && ! filter_var($next['office_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['office_email'] = __('admin.library_settings_error_office_email');
        }
        if ($next['office_phone'] !== '' && ! preg_match('/^\+?[0-9 ]{7,20}$/', $next['office_phone'])) {
            $errors['office_phone'] = __('admin.library_settings_error_office_phone');
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
