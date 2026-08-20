<?php

namespace AwStudio\ModelIndex;

use Illuminate\Support\ServiceProvider;

class ModelIndexServiceProvider extends ServiceProvider
{
    /**
     * Register the package's configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/model-index.php', 'model-index');
    }

    /**
     * Bootstrap the package.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/model-index.php' => $this->app->configPath('model-index.php'),
            ], 'model-index-config');
        }
    }
}
