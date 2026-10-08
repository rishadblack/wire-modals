<?php

use Livewire\Livewire;

it('runs the action again when a confirm alert is answered', function () {
    Livewire::test('legacy.note-edit')
        ->dispatch('onConfirmAlert', data: ['inputAttributes' => ['component' => 'legacy.note-edit', 'action' => 'noteDelete', 'id' => 7, 'isConfirmed' => true]])
        ->assertSet('title', 'Deleted 7')
        ->assertDispatched('wire-modals:dispose', target: 'legacy.note-edit');
});

it('accepts an unwrapped payload on the confirm event', function () {
    Livewire::test('legacy.note-edit')
        ->dispatch('onConfirmAlert', data: ['component' => 'legacy.note-edit', 'action' => 'openNoteModal', 'id' => 2])
        ->assertSet('note_id', 2);
});

it('disposes the component when the alert is dismissed', function () {
    Livewire::test('legacy.note-edit')
        ->dispatch('onCancelAlert', data: ['inputAttributes' => ['component' => 'legacy.note-edit', 'action' => 'noteDelete']])
        ->assertDispatched('wire-modals:dispose', target: 'legacy.note-edit');
});

it('still gates the action on the confirm round-trip', function () {
    Livewire::test('legacy.note-edit')
        ->dispatch('onConfirmAlert', data: ['inputAttributes' => ['component' => 'legacy.note-edit', 'action' => 'render', 'isConfirmed' => true]])
        ->assertDispatched('wire-modals:rejected', action: 'render');
});

it('answers the historical alreadyExistsComponent event', function () {
    Livewire::test('legacy.note-edit')
        ->dispatch('alreadyExistsComponent', data: ['component' => 'legacy.note-edit', 'action' => 'openNoteModal', 'id' => 5])
        ->assertSet('note_id', 5);
});
