<?php

namespace Avexsoft\Donkey;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;

class DonkeyServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'avexsoft');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'avexsoft');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        $paths = [__DIR__.'/../database/migrations'];
        $this->callAfterResolving('migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                $migrator->path($path);
            }
        });

    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/donkey.php', 'donkey');

        // Register the service the package provides.
        $this->app->singleton('donkey', function ($app) {
            return new Donkey;
        });

        $this->app->booting(function () {
            // this runs after the entire framework is booted
            $isCached = $this->app instanceof CachesConfiguration && $this->app->configurationIsCached();
            if (! $isCached) {
                // find a way to play nicely with Laravel's cache (art optimize)
                // so that below does not have to run on every request
                $src = storage_path('config/values.json');
                if (file_exists($src)) {
                    $config = json_decode(file_get_contents($src), true);
                    foreach ($config as $key => $value) {
                        $a = json_decode($value, true);
                        if (is_array($a)) {
                            // treat as JSON
                            config([$key => $a]);
                        } else {
                            // treat as string
                            config([$key => $value]);
                        }
                    }
                }
            }
        });

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['donkey'];
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/donkey.php' => config_path('donkey.php'),
        ], 'donkey.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/avexsoft'),
        ], 'donkey.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/avexsoft'),
        ], 'donkey.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/avexsoft'),
        ], 'donkey.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
