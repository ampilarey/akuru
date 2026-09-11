<?php

namespace App\Domains\Offerings\Providers;

use App\Domains\Offerings\Contracts\VideoConferencingInterface;
use App\Domains\Offerings\Services\BigBlueButtonVideoConferencing;
use App\Domains\Offerings\Services\NullVideoConferencing;
use Illuminate\Support\ServiceProvider;

class OfferingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ROADMAP §2d. The default is deliberately the null implementation, not
        // an exception: Level 1 (a teacher pasting a meeting link onto a
        // session) is a supported configuration, and the engine must work fully
        // without a provider. A school only reaches Level 2 by configuring one.
        $this->app->singleton(VideoConferencingInterface::class, function () {
            $driver = (string) config('offerings.video.driver', 'null');
            $base = (string) config('offerings.video.bigbluebutton.base_url', '');
            $secret = (string) config('offerings.video.bigbluebutton.secret', '');

            if ($driver !== 'bigbluebutton' || $base === '' || $secret === '') {
                return new NullVideoConferencing;
            }

            return new BigBlueButtonVideoConferencing(
                baseUrl: $base,
                secret: $secret,
                timeoutSeconds: (int) config('offerings.video.timeout_seconds', 10),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
