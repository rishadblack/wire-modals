<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Rishadblack\WireModals\Exceptions\InvalidPayloadException;
use Rishadblack\WireModals\Support\Trigger;
use Rishadblack\WireModals\Tests\Fixtures\NoteEdit;

it('builds the trigger attributes', function () {
    $attributes = Trigger::make('notes.note-edit', 'openNoteModal')
        ->id(7)
        ->key('notes-7')
        ->data(['folder_id' => 3, 'orderTypeId' => 'abc', 'archived' => true])
        ->attributes();

    expect($attributes)->toBe([
        'wire:modal' => 'notes.note-edit',
        'wire:modal.action' => 'openNoteModal',
        'wire:modal.id' => '7',
        'wire:modal.key' => 'notes-7',
        'data-folder-id' => '3',
        'data-order-type-id' => 'abc',
        'data-archived' => 'true',
    ]);
});

it('omits optional attributes that were not set', function () {
    expect(Trigger::make('notes.note-edit')->attributes())->toBe(['wire:modal' => 'notes.note-edit']);
});

it('escapes values when rendered as html', function () {
    $html = Trigger::make('notes.note-edit', 'openNoteModal')->id('7" onclick="alert(1)')->with('note', "<b>'&")->toHtml();

    expect($html)->toContain('wire:modal.id="7&quot; onclick=&quot;alert(1)"')
        ->toContain('data-note="&lt;b&gt;&#039;&amp;"')
        ->not->toContain('<b>');
});

it('rejects invalid component and action names at build time', function () {
    expect(fn () => Trigger::make('bad name'))->toThrow(InvalidPayloadException::class)
        ->and(fn () => Trigger::make('notes.note-edit', '__dispatch'))->toThrow(InvalidPayloadException::class);
});

it('rejects nested data because attributes are flat', function () {
    Trigger::make('notes.note-edit')->data(['nested' => ['a' => 1]]);
})->throws(TypeError::class);

it('is pre-filled by the trait with the component name', function () {
    expect(NoteEdit::modalTrigger('openNoteModal')->attributes()['wire:modal'])->toBe('notes.note-edit');
});

it('renders the Blade trigger component', function () {
    $html = Blade::render('<x-wire-modals::trigger component="notes.note-edit" action="openNoteModal" :record-id="7" :data="[\'folder_id\' => 3]" class="btn btn-primary">Edit</x-wire-modals::trigger>');

    expect($html)->toContain('<button')
        ->toContain('type="button"')
        ->toContain('class="btn btn-primary"')
        ->toContain('wire:modal="notes.note-edit"')
        ->toContain('wire:modal.action="openNoteModal"')
        ->toContain('wire:modal.id="7"')
        ->toContain('data-folder-id="3"')
        ->toContain('>Edit</button>');
});

it('keeps a plain id attribute on the element instead of treating it as the record id', function () {
    $html = Blade::render('<x-wire-modals::trigger component="notes.note-edit" id="open-options">Options</x-wire-modals::trigger>');

    expect($html)->toContain('id="open-options"')->not->toContain('wire:modal.id');
});

it('renders the Blade trigger as a link when asked', function () {
    $html = Blade::render('<x-wire-modals::trigger component="notes.note-edit" tag="a">Edit</x-wire-modals::trigger>');

    expect($html)->toContain('<a')->toContain('href="#"')->toContain('role="button"')->toContain('</a>');
});

it('refuses unknown trigger tags', function () {
    Blade::render('<x-wire-modals::trigger component="notes.note-edit" tag="script">x</x-wire-modals::trigger>');
})->throws(ViewException::class, 'Trigger tag must be one of');
