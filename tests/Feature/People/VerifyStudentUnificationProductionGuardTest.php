<?php

it('refuses --backfill when the app environment is production', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        $this->artisan('students:verify-unification', ['--backfill' => true])
            ->expectsOutputToContain('Refusing --backfill on production')
            ->assertFailed();
    } finally {
        $this->app['env'] = $previous;
    }
});
