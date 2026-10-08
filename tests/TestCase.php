<?php

namespace Rishadblack\WireModals\Tests;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Rishadblack\WireModals\Tests\Fixtures\LegacyNoteEdit;
use Rishadblack\WireModals\Tests\Fixtures\NoteEdit;
use Rishadblack\WireModals\Tests\Fixtures\PlainComponent;
use Rishadblack\WireModals\Tests\Fixtures\TagEdit;
use Rishadblack\WireModals\WireModalsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireModalsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('fixtures', __DIR__.'/Fixtures/views');

        Livewire::component('notes.note-edit', NoteEdit::class);
        Livewire::component('tags.tag-edit', TagEdit::class);
        Livewire::component('plain', PlainComponent::class);
        Livewire::component('legacy.note-edit', LegacyNoteEdit::class);

        NoteEdit::$denyActions = [];
    }
}
