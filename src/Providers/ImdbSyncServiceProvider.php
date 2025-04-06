<?php

namespace namhuunam\ImdbSync\Providers;

use Illuminate\Support\ServiceProvider;
use namhuunam\ImdbSync\Commands\SyncImdbRatings;
use namhuunam\ImdbSync\Services\OmdbApiService;

class ImdbSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/imdb-sync.php', 'imdb-sync'
        );

        $this->app->singleton(OmdbApiService::class, function ($app) {
            return new OmdbApiService(config('imdb-sync.api_keys'));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/imdb-sync.php' => config_path('imdb-sync.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Register migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncImdbRatings::class,
            ]);
        }
    }
}