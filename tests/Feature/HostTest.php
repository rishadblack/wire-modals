<?php

use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Rishadblack\WireModals\Http\Livewire\Host;

function notePayload(array $overrides = []): array
{
    return array_merge(['component' => 'notes.note-edit', 'action' => 'openNoteModal', 'id' => 7], $overrides);
}

it('mounts the component with the payload on first load', function () {
    $host = Livewire::test(Host::class)->call('load', notePayload(['data' => ['folder' => 'inbox']]));

    $host->assertSee('Note 7')
        ->assertSee('inbox')
        ->assertNotDispatched('wire-modals:rejected');

    expect($host->get('components'))->toHaveKey('notes.note-edit')
        ->and($host->get('components')['notes.note-edit']['id'])->toBe(7);
});

it('renders the host root with its runtime settings', function () {
    Livewire::test(Host::class)
        ->assertSeeHtml('data-wire-modals-host')
        ->assertSeeHtml('x-data="wireModalsHost"')
        ->assertSeeHtml('data-wire-modals-legacy-events="false"')
        ->assertSeeHtml('data-wire-modals-data-exclusions="bs-,wire-modals-"');
});

it('forwards the payload to the mounted instance on a second load', function () {
    $host = Livewire::test(Host::class)
        ->call('load', notePayload())
        ->call('load', notePayload(['id' => 8]))
        ->assertDispatchedTo('notes.note-edit', 'wire-modals:already-mounted');

    expect($host->get('components'))->toHaveCount(1);
});

it('mounts a second instance when the key differs', function () {
    $host = Livewire::test(Host::class)
        ->call('load', notePayload())
        ->call('load', notePayload(['key' => 'second', 'id' => 9]));

    expect($host->get('components'))->toHaveKeys(['notes.note-edit', 'second']);

    // Livewire only re-sends the HTML of children mounted in this request.
    $host->assertSee('Note 9')->assertSeeHtml('wire-modals-notes-note-edit')->assertSeeHtml('wire-modals-second');
});

it('unmounts every entry of a component', function () {
    $host = Livewire::test(Host::class)
        ->call('load', notePayload())
        ->call('load', notePayload(['key' => 'second']))
        ->call('load', ['component' => 'tags.tag-edit', 'action' => 'openTagModal', 'id' => 1])
        ->call('unmount', 'notes.note-edit');

    expect($host->get('components'))->toHaveCount(1)->toHaveKey('tags.tag-edit');

    $host->assertDontSee('Note 7');
});

it('ignores unmount for a component that is not mounted', function () {
    $host = Livewire::test(Host::class)->call('load', notePayload())->call('unmount', 'tags.tag-edit');

    expect($host->get('components'))->toHaveCount(1);
});

it('cannot have its component list changed from the browser', function () {
    Livewire::test(Host::class)->set('components', [['component' => 'plain', 'action' => 'mount']]);
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses to mount more than max_mounted components', function () {
    config()->set('wire-modals.max_mounted', 1);
    Log::spy();

    $host = Livewire::test(Host::class)
        ->call('load', notePayload())
        ->call('load', ['component' => 'tags.tag-edit', 'action' => 'openTagModal']);

    $host->assertDispatched('wire-modals:rejected');

    expect($host->get('components'))->toHaveCount(1);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => str_contains($message, 'max_mounted') && $context['component'] === 'tags.tag-edit')->once();
});

it('rejects payloads naming an unknown, plain or disallowed component', function (array $payload, string $reason) {
    config()->set('wire-modals.allowed_components', ['notes.*', 'tags.*', 'plain']);

    $host = Livewire::test(Host::class)->call('load', $payload);

    $host->assertDispatched('wire-modals:rejected', fn (string $name, array $params) => str_contains($params['reason'], $reason));

    expect($host->get('components'))->toBe([]);
})->with([
    'unknown' => [['component' => 'notes.missing', 'action' => 'openXModal'], 'not a registered'],
    'without trait' => [['component' => 'plain', 'action' => 'mount'], 'WithModals'],
    'not allowed' => [['component' => 'other.thing', 'action' => 'openXModal'], 'allowed_components'],
    'class name' => [['component' => 'Rishadblack\\WireModals\\Tests\\Fixtures\\NoteEdit', 'action' => 'openNoteModal'], 'class name'],
    'invalid payload' => [['action' => 'openNoteModal'], 'no [component]'],
]);

it('refuses an action that is not callable before mounting anything', function (array $payload, string $reason) {
    $host = Livewire::test(Host::class)->call('load', $payload);

    $host->assertDispatched('wire-modals:rejected', fn (string $name, array $params) => str_contains($params['reason'], $reason))
        ->assertDontSee('Note')
        ->assertDontSee('tag-id');

    expect($host->get('components'))->toBe([]);
})->with([
    'lifecycle method' => [['component' => 'notes.note-edit', 'action' => 'render'], 'not callable'],
    'plain public method' => [['component' => 'notes.note-edit', 'action' => 'notAnAction'], 'not callable'],
    'no action and no default' => [['component' => 'tags.tag-edit'], 'default'],
]);

it('does not log rejections when logging is disabled', function () {
    config()->set('wire-modals.log_rejections', false);
    Log::spy();

    Livewire::test(Host::class)->call('load', ['component' => 'does.not-exist', 'action' => 'openXModal']);

    Log::shouldNotHaveReceived('warning');
});

it('never logs payload data', function () {
    Log::spy();

    Livewire::test(Host::class)->call('load', ['component' => 'does.not-exist', 'action' => 'openXModal', 'data' => ['secret' => 'value']]);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), 'value'))->once();
});

it('strips control characters and caps the length of what it logs about a rejected payload', function () {
    Log::spy();

    Livewire::test(Host::class)->call('load', ['component' => "bad\nname".str_repeat('x', 300), 'action' => "open\r\nModal"]);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        return $context['component'] === 'badname'.str_repeat('x', 113)
            && $context['action'] === 'openModal';
    })->once();
});

it('answers the legacy events only when legacy mode is on', function () {
    $listeners = fn () => (new ReflectionMethod(Host::class, 'getListeners'))->invoke(new Host);

    expect($listeners())->toBe([]);

    config()->set('wire-modals.legacy.events', true);

    expect($listeners())->toBe(['loadComponent' => 'loadComponent', 'closeComponent' => 'closeComponent']);
});

it('mounts and unmounts through the legacy events', function () {
    config()->set('wire-modals.legacy.events', true);

    $host = Livewire::test(Host::class)
        ->dispatch('loadComponent', action: 'openNoteModal', component: 'notes.note-edit', id: '7', data: ['folder' => 'legacy'])
        ->assertSee('Note 7')
        ->assertSee('legacy');

    expect($host->get('components'))->toHaveKey('notes.note-edit');

    $host->dispatch('closeComponent', 'notes.note-edit');

    expect($host->get('components'))->toBe([]);
});
