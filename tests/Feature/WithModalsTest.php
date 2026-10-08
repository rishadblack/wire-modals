<?php

use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Rishadblack\WireModals\Tests\Fixtures\NoteEdit;

it('runs the action from the mount payload and opens the modal', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'openNoteModal', 'id' => 7, 'data' => ['folder' => 'inbox']]])
        ->assertSet('note_id', 7)
        ->assertSet('folder', 'inbox')
        ->assertDispatched('wire-modals:open', modal: 'NoteModal', owner: 'notes.note-edit')
        ->assertNotDispatched('wire-modals:rejected');
});

it('does nothing when mounted without a payload', function () {
    Livewire::test('notes.note-edit')
        ->assertSet('calls', [])
        ->assertNotDispatched('wire-modals:open');
});

it('passes the canonical payload array to the action', function () {
    $component = Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'openNoteModal', 'id' => '7', 'data' => ['folder' => 'x']]]);

    expect($component->get('calls')[0])->toBe([
        'id' => '7',
        'data' => ['folder' => 'x'],
        'action' => 'openNoteModal',
        'component' => 'notes.note-edit',
        'modal' => null,
        'key' => 'notes.note-edit',
    ]);
});

it('re-runs an action when the host says it is already mounted', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'id' => 1]])
        ->dispatch('wire-modals:already-mounted', payload: ['component' => 'notes.note-edit', 'action' => 'openNoteModal', 'id' => 2])
        ->assertSet('note_id', 2)
        ->assertDispatched('wire-modals:open', modal: 'NoteModal', owner: 'notes.note-edit');
});

it('uses the default action when the payload names none', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'id' => 3]])
        ->assertSet('note_id', 3)
        ->assertDispatched('wire-modals:open');
});

it('rejects a payload without action on a component without default action', function () {
    Livewire::test('tags.tag-edit', ['wireModalsPayload' => ['component' => 'tags.tag-edit', 'id' => 3]])
        ->assertSet('tag_id', null)
        ->assertDispatched('wire-modals:rejected', fn (string $name, array $params) => str_contains($params['reason'], 'default'));
});

it('rejects actions that are not callable', function (string $action) {
    Log::spy();

    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => $action]])
        ->assertSet('calls', [])
        ->assertNotDispatched('wire-modals:open')
        ->assertDispatched('wire-modals:rejected', action: $action);

    Log::shouldHaveReceived('warning')->once();
})->with(['notAnAction', 'secretHelper', 'render', 'dispatch', 'save', 'closeComponent', 'nope']);

it('rejects a malformed payload', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => '__dispatch']])
        ->assertSet('calls', [])
        ->assertDispatched('wire-modals:rejected');
});

it('disposes itself when the mount action is refused', function () {
    NoteEdit::$denyActions = ['openNoteModal'];

    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'openNoteModal']])
        ->assertDispatched('wire-modals:rejected')
        ->assertDispatched('wire-modals:dispose', target: 'notes.note-edit');
});

it('does not dispose itself when a re-entry action is refused', function () {
    NoteEdit::$denyActions = ['openNoteModal'];

    Livewire::test('notes.note-edit')
        ->dispatch('wire-modals:already-mounted', payload: ['component' => 'notes.note-edit', 'action' => 'openNoteModal'])
        ->assertDispatched('wire-modals:rejected')
        ->assertNotDispatched('wire-modals:dispose');
});

it('lets authorizeModalAction refuse an action', function () {
    NoteEdit::$denyActions = ['openNoteModal'];

    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'openNoteModal']])
        ->assertSet('calls', [])
        ->assertDispatched('wire-modals:rejected', fn (string $name, array $params) => str_contains($params['reason'], 'authorizeModalAction'));
});

it('passes the canonical action name to authorizeModalAction whatever casing the client used', function () {
    NoteEdit::$denyActions = ['noteDelete'];

    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'NoteDelete', 'id' => 7, 'isConfirmed' => true]])
        ->assertSet('calls', [])
        ->assertDispatched('wire-modals:rejected', action: 'NoteDelete');

    NoteEdit::$denyActions = [];

    $component = Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'NoteDelete', 'id' => 7, 'isConfirmed' => true]]);

    expect($component->get('calls')[0]['action'])->toBe('noteDelete');
});

it('never writes properties from the payload', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'openNoteModal', 'data' => ['extra' => ['a', 'b', 'title:hacked']]]])
        ->assertSet('title', 'New note');
});

it('opens a sub-modal without tying it to the component lifetime', function () {
    Livewire::test('notes.note-edit')
        ->call('openOptionsModal')
        ->assertDispatched('wire-modals:open', modal: 'OptionsModal', owner: null);
});

it('closes a modal and disposes the component', function () {
    Livewire::test('notes.note-edit')
        ->call('save')
        ->assertDispatched('wire-modals:close', modal: 'NoteModal')
        ->call('closeComponent')
        ->assertDispatched('wire-modals:dispose', target: 'notes.note-edit')
        ->call('closeComponent', 'tags.tag-edit')
        ->assertDispatched('wire-modals:dispose', target: 'tags.tag-edit');
});

it('runs a confirmed delete and disposes itself', function () {
    Livewire::test('notes.note-edit', ['wireModalsPayload' => ['component' => 'notes.note-edit', 'action' => 'noteDelete', 'id' => 7, 'isConfirmed' => true]])
        ->assertSet('title', 'Deleted 7')
        ->assertDispatched('wire-modals:dispose', target: 'notes.note-edit');
});
