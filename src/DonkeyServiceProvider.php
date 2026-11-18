<?php

namespace Avexsoft\Donkey;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class DonkeyServiceProvider extends ServiceProvider
{
    protected array $originalConfigValues = [];

    /**
     * View namespaces Laravel only registers when debug mode is enabled.
     *
     * `Illuminate\Foundation\Providers\FoundationServiceProvider::register()`
     * registers these before any package provider is registered. When Donkey
     * later disables `app.debug`, the views stay registered even though the
     * application no longer considers itself to be in debug mode.
     *
     * @var string[]
     */
    protected const DEBUG_ONLY_VIEW_NAMESPACES = [
        'laravel-exceptions-renderer',
    ];

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'avexsoft');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'avexsoft');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/override.php', 'override');

        $this->app->singleton('donkey', function ($app) {
            return new Donkey();
        });

        // The stored values have to be applied while the application is still
        // registering its providers. Doing this in a `booting()` callback is
        // too late for providers (Laravel's own included) that read a config
        // value from `register()`.
        $this->applyStoredConfigValues();

        $this->app->booted(function () {
            if ($this->originalConfigValues && Schema::hasColumn('overrides', 'original_config_value')) {
                foreach ($this->originalConfigValues as $key => $originalValue) {
                    DB::table('overrides')
                        ->where('key', $key)
                        ->update(['original_config_value' => $originalValue]);
                }
            }
        });
    }

    /**
     * Inject the values stored in `storage/config/values.json` into the config.
     */
    protected function applyStoredConfigValues(): void
    {
        // The cached config already contains these values, they were applied
        // when the cache was built.
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $src = storage_path('config/values.json');

        if (! file_exists($src)) {
            return;
        }

        $values = json_decode(file_get_contents($src), true);

        if (! is_array($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            $this->originalConfigValues[$key] = config($key);

            // Arrays are stored as a JSON encoded string, decode them back
            // into their original shape.
            $decoded = is_string($value) ? json_decode($value, true) : null;

            config([$key => is_array($decoded) ? $decoded : $value]);
        }

        $this->forgetDebugOnlyViews();
    }

    /**
     * Remove the views Laravel only registers while debug mode is enabled.
     *
     * Laravel registers its exception renderer views in
     * `FoundationServiceProvider::register()`, before Donkey has a chance to
     * override `app.debug`. When Donkey disables debug mode those views stay
     * behind, but the containers Laravel boots for `config:cache` and
     * `route:cache` are created from the cached (debug disabled) config and do
     * not have them. `art optimize` then fails while `view:cache` compiles the
     * debug-only exception views:
     *
     *     Unable to locate a class or view for component
     *     [laravel-exceptions-renderer::topbar].
     *
     * Removing the namespaces keeps the view finder consistent with the final
     * value of `app.debug`.
     */
    protected function forgetDebugOnlyViews(): void
    {
        $forget = function ($view) {
            if (config('app.debug')) {
                return;
            }

            $finder = $view->getFinder();
            $hints = $finder->getHints();

            foreach (static::DEBUG_ONLY_VIEW_NAMESPACES as $namespace) {
                if (isset($hints[$namespace])) {
                    $finder->replaceNamespace($namespace, []);
                }
            }
        };

        if ($this->app->resolved('view')) {
            $forget($this->app['view']);

            return;
        }

        $this->app->afterResolving('view', $forget);
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
            __DIR__.'/../config/override.php' => config_path('override.php'),
        ], 'donkey.override');

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
