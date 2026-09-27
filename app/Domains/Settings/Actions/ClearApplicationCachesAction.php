<?php

namespace App\Domains\Settings\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * The *Clear all caches* button on `/admin/settings` (admin-panel audit
 * finding 13, STATUS §5il).
 *
 * It used to run `config:clear` outright, so a production host that had
 * deployed with `config:cache` (the deploy line does) ran uncached from that
 * click until the next deploy — every request re-reading forty config files
 * and `.env`. Now, when the configuration was cached, it is **rebuilt**
 * rather than dropped: `config:cache` clears and recompiles in one step, so
 * the host sees the fresh values and stays as fast as it was deployed.
 * Where nothing was cached (local, staging without the deploy line) the
 * old `config:clear` is what there is to do.
 *
 * Routes are only ever cleared, never cached: the deploy line does not
 * `route:cache` and this button must not start.
 */
final class ClearApplicationCachesAction
{
    /**
     * @return list<string> the artisan commands run, in order
     */
    public function execute(bool $configurationWasCached): array
    {
        $commands = [
            'cache:clear',
            'view:clear',
            'route:clear',
            $configurationWasCached ? 'config:cache' : 'config:clear',
        ];

        foreach ($commands as $command) {
            Artisan::call($command);
        }

        return $commands;
    }
}
