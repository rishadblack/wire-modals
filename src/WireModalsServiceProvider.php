<?php

namespace Rishadblack\WireModals;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Rishadblack\WireModals\Http\Livewire\Host;

class WireModalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wire-modals.php', 'wire-modals');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wire-modals');

        Blade::componentNamespace('Rishadblack\\WireModals\\View\\Components', 'wire-modals');

        Livewire::component((string) config('wire-modals.host_component', 'wire-modals.host'), Host::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wire-modals.php' => config_path('wire-modals.php'),
            ], 'wire-modals-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/wire-modals'),
            ], 'wire-modals-views');
        }
    }
}
