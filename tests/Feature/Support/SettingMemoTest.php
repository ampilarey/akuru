<?php

use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Settings are read once per request (ADMIN_PANEL.md §7 P4, STATUS §5nn).
 * `Setting::get()` ran `select * from settings` on every call — twelve times
 * on the Library settings screen. The table is now remembered in the
 * container for the rest of the request, and a write forgets it, so what
 * was saved is what is read next.
 */
function settingsQueries(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`settings`'))->count();
    DB::disableQueryLog();

    return $count;
}

it('reads the table once for many keys', function () {
    app(SetSettingAction::class)->execute('memo_a', 'A');
    app(SetSettingAction::class)->execute('memo_b', '1', 'boolean');
    Setting::forgetMemo();

    $queries = settingsQueries(function () {
        expect(Setting::get('memo_a'))->toBe('A')
            ->and(Setting::get('memo_b'))->toBeTrue()
            ->and(Setting::get('memo_missing', 'fallback'))->toBe('fallback')
            ->and(Setting::allKeyed()->get('memo_a'))->toBe('A');
    });

    expect($queries)->toBe(1);
});

it('forgets what it read when a setting is written, through the Action and through the model', function () {
    app(SetSettingAction::class)->execute('memo_c', 'before');
    expect(Setting::get('memo_c'))->toBe('before');

    app(SetSettingAction::class)->execute('memo_c', 'after');
    expect(Setting::get('memo_c'))->toBe('after');

    Setting::set('memo_c', 'later');
    expect(Setting::get('memo_c'))->toBe('later');
});
