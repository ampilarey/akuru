<?php

namespace App\Domains\Settings\Actions;

/**
 * The office switches Dhivehi and Arabic on or off (STATUS §5su). English
 * has no switch. Nothing is deleted: a language turned back on is as it was.
 */
class SetEnabledLocalesAction
{
    /**
     * @param  array<string, bool>  $states  keyed by language, only the switchable ones are read
     */
    public function execute(array $states): void
    {
        foreach (ListEnabledLocalesAction::SWITCHABLE as $locale) {
            if (array_key_exists($locale, $states)) {
                app(SetSettingAction::class)->execute(ListEnabledLocalesAction::settingKey($locale), (bool) $states[$locale], 'boolean', 'locale');
            }
        }
    }
}
