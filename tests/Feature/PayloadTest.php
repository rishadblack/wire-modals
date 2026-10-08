<?php

use Rishadblack\WireModals\Exceptions\InvalidPayloadException;
use Rishadblack\WireModals\Support\Payload;

it('builds the canonical array an action receives', function () {
    $payload = Payload::fromClient([
        'component' => ' notes.note-edit ',
        'action' => 'openNoteModal',
        'id' => '7',
        'data' => ['folder' => '3', 'tags' => ['a', 'b']],
        'modal' => 'NoteModal',
    ]);

    expect($payload->toArray())->toBe([
        'id' => '7',
        'data' => ['folder' => '3', 'tags' => ['a', 'b']],
        'action' => 'openNoteModal',
        'component' => 'notes.note-edit',
        'modal' => 'NoteModal',
        'key' => 'notes.note-edit',
    ]);
});

it('accepts the component@action shorthand', function () {
    $payload = Payload::fromClient(['component' => 'notes.note-edit@noteDelete']);

    expect($payload->component)->toBe('notes.note-edit')
        ->and($payload->action)->toBe('noteDelete');
});

it('rejects the shorthand when an explicit action is also given', function () {
    Payload::fromClient(['component' => 'notes.note-edit@noteDelete', 'action' => 'openNoteModal']);
})->throws(InvalidPayloadException::class, '[component] contains');

it('keeps isConfirmed and drops unknown keys', function () {
    $payload = Payload::fromClient(['component' => 'notes.note-edit', 'isConfirmed' => true, 'extra' => 'x', 'inputAttributes' => []]);

    expect($payload->toArray())->toHaveKey('isConfirmed', true)
        ->not->toHaveKey('extra')
        ->not->toHaveKey('inputAttributes');
});

it('uses a custom key and slugs it for the DOM', function () {
    $payload = Payload::fromClient(['component' => 'notes.note-edit', 'key' => 'orders:2']);

    expect($payload->key)->toBe('orders:2')->and($payload->slug())->toBe('orders-2');
});

it('casts an integer id and treats an empty id as null', function () {
    expect(Payload::fromClient(['component' => 'c', 'id' => 5])->id)->toBe(5)
        ->and(Payload::fromClient(['component' => 'c', 'id' => ''])->id)->toBeNull();
});

it('rejects invalid input', function (array $raw, string $message) {
    expect(fn () => Payload::fromClient($raw))->toThrow(InvalidPayloadException::class, $message);
})->with([
    'missing component' => [['action' => 'openNoteModal'], 'no [component]'],
    'component with spaces' => [['component' => 'notes note'], '[component] contains'],
    'component with html' => [['component' => '<b>'], '[component] contains'],
    'class name by default' => [['component' => 'App\\Livewire\\NoteEdit'], 'class name'],
    'magic action' => [['component' => 'c', 'action' => '__dispatch'], '[action] is not a valid'],
    'action with spaces' => [['component' => 'c', 'action' => 'open Note'], '[action] is not a valid'],
    'array id' => [['component' => 'c', 'id' => [1]], '[id] must be'],
    'boolean id' => [['component' => 'c', 'id' => true], '[id] must be'],
    'long id' => [['component' => 'c', 'id' => str_repeat('a', 192)], '[id] is longer'],
    'data not array' => [['component' => 'c', 'data' => 'x'], '[data] must be an array'],
    'data too deep' => [['component' => 'c', 'data' => ['a' => ['b' => ['c' => ['d' => 1]]]]], 'nests deeper'],
    'data key __proto__' => [['component' => 'c', 'data' => ['__proto__' => 1]], 'invalid key'],
    'data key with dash' => [['component' => 'c', 'data' => ['order-type' => 1]], 'invalid key'],
    'data object value' => [['component' => 'c', 'data' => ['o' => new stdClass]], 'scalar values'],
    'too many values' => [['component' => 'c', 'data' => array_fill(0, 51, 'x')], 'more than 50'],
    'bad key' => [['component' => 'c', 'key' => 'a b'], '[key] contains'],
    'component walking upwards' => [['component' => 'notes..secret'], 'malformed'],
    'component starting with a dot' => [['component' => '.hidden'], '[component] contains'],
    'component ending with a separator' => [['component' => 'notes.'], 'malformed'],
    'non string action' => [['component' => 'c', 'action' => ['x']], '[action] must be a string'],
]);

it('rejects data larger than the configured byte limit', function () {
    config()->set('wire-modals.max_payload_bytes', 20);

    expect(fn () => Payload::fromClient(['component' => 'c', 'data' => ['text' => str_repeat('x', 30)]]))
        ->toThrow(InvalidPayloadException::class, 'larger than 20 bytes');
});

it('allows class names when configured', function () {
    config()->set('wire-modals.allow_class_names', true);

    expect(Payload::fromClient(['component' => 'App\\Livewire\\NoteEdit'])->component)->toBe('App\\Livewire\\NoteEdit');
});
