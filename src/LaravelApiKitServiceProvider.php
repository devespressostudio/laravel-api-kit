<?php

namespace Devespresso\LaravelApiKit;

use Devespresso\LaravelApiKit\Commands\ScaffoldCommand;
use Illuminate\Support\ServiceProvider;

class LaravelApiKitServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/devespressoApi.php' => config_path('devespressoApi.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScaffoldCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/devespressoApi.php',
            'devespressoApi'
        );
    }
}
