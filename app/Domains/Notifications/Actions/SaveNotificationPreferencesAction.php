<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\NotificationPreference;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreferencesAction
{
    /**
     * @param  array<string, bool>  $choices  category => wanted
     */
    public function execute(int $userId, array $choices): void
    {
        $selectable = array_keys(ResolveNotificationPreferencesAction::CATEGORIES);

        DB::transaction(function () use ($userId, $choices, $selectable): void {
            foreach ($choices as $category => $enabled) {
                // Anything not on the list is ignored rather than stored: a
                // hand-posted category would otherwise sit in the table
                // silently suppressing a notification nobody can re-enable.
                if (! in_array($category, $selectable, true)) {
                    continue;
                }

                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $userId, 'category' => $category],
                    ['enabled' => (bool) $enabled],
                );
            }
        });
    }
}
