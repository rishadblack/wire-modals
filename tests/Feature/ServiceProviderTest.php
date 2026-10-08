<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Rishadblack\WireModals\Http\Livewire\Host;
use Rishadblack\WireModals\WireModalsServiceProvider;

it('merges the package config', function () {
    expect(config('wire-modals.host_component'))->toBe('wire-modals.host')
        ->and(config('wire-modals.action_patterns'))->toBe(['open*Modal'])
        ->and(config('wire-modals.legacy.events'))->toBeFalse();
});

it('registers the host component under the configured name', function () {
    expect(app('livewire')->new('wire-modals.host'))->toBeInstanceOf(Host::class);
});

it('registers the Blade component namespace', function () {
    $html = Blade::render('<x-wire-modals::modal id="DemoModal">Body</x-wire-modals::modal>');

    expect($html)->toContain('id="DemoModal"')->toContain('modal-body');
});

it('publishes config and views', function () {
    expect(ServiceProvider::pathsToPublish(WireModalsServiceProvider::class, 'wire-modals-config'))->not->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(WireModalsServiceProvider::class, 'wire-modals-views'))->not->toBeEmpty();
});
