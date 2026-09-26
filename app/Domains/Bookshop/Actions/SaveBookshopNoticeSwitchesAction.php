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
    }
}
