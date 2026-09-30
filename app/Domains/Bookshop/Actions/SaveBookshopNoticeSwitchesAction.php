<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Settings\Actions\SetSettingAction;

/**
 * The office's email and SMS switches for bookstore notices (BOOKSHOP_PLAN
 * §7 Settings "email/SMS notice switches", slice B8): customer email,
 * customer SMS, shop email, shop SMS. Stored as settings (the Settings
 * domain's writer, rule 3), read by `NotifyBookshopUserAction`.
 */
class SaveBookshopNoticeSwitchesAction
{
    /**
     * @param  array<string, mixed>  $switches
     */
    public function execute(array $switches): void
    {
        foreach (array_keys((array) config('bookshop.notices.office_defaults')) as $key) {
            app(SetSettingAction::class)->execute(
                NotifyBookshopUserAction::SETTING_PREFIX.$key,
                (bool) ($switches[$key] ?? false),
                'boolean',
                'bookshop',
                'Bookstore notices: '.str_replace('_', ' ', $key),
            );
        }
        // COMMERCE_PARITY_PLAN P5: where the office's own copy of a purchase notice goes.
        foreach (['office_contact_email' => 'bookshop_office_email', 'office_contact_phone' => 'bookshop_office_phone'] as $field => $setting) {
            if (array_key_exists($field, $switches)) {
                app(SetSettingAction::class)->execute($setting, trim((string) $switches[$field]), 'string', 'bookshop', 'Bookstore office '.($field === 'office_contact_email' ? 'email' : 'phone').' for purchase notices');
            }
        }
    }
}
