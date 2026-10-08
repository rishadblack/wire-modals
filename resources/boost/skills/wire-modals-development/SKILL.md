---
name: wire-modals-development
description: Use this skill when building on-demand Livewire modal components with rishadblack/wire-modals. Trigger when creating or editing classes that use Rishadblack\WireModals\Traits\WithModals, methods named open*Modal or marked #[ModalAction], views with <x-wire-modals::modal>, triggers written as wire:modal / wire:modal.action / wire:modal.id / data-* attributes or <x-wire-modals::trigger>, the Trigger builder for table action columns, modalOpen / modalClose / closeComponent calls, sub-modals with keepComponent, delete-with-confirmation flows, the authorizeModalAction hook, the <livewire:wire-modals.host /> layout tag, config/wire-modals.php, legacy mode for migrated apps, or testing these components with Pest.
---

# Wire Modals Development

`rishadblack/wire-modals` mounts a Livewire component the first time a button is clicked, calls an action on it, keeps it alive while its Bootstrap 5 modal is open, and unmounts it when the modal closes. The component owns its modal markup, so one component can hold an edit modal, an options sub-modal and a delete confirmation. The browser side is one small script (a `wire:modal` directive and two Alpine components); you write no JavaScript.

## When to Use This Package

- Use it for create / edit / view / delete modals opened from lists, tables and toolbars, and for any component that should exist only while its dialog is open.
- Do not use it for static dialogs that are always on the page (a plain Bootstrap modal is enough) or for non-Livewire pages.

## Requirements and Setup

- PHP 8.3+, Laravel 11–13, Livewire 3 or 4 (Alpine comes with Livewire). Bootstrap 5.3 must be loaded as `window.bootstrap`.
- Import the package script **after** Bootstrap, before Livewire starts:

```js
// resources/js/app.js
import 'bootstrap';
import '../../vendor/rishadblack/wire-modals/resources/js/wire-modals.js';
```

- Put the host once in the layout, anywhere in `<body>`:

```blade
<livewire:wire-modals.host />
```

- Optional: `@import '../../vendor/rishadblack/wire-modals/resources/css/wire-modals.css';` in `app.css`, and `php artisan vendor:publish --tag=wire-modals-config`.
- Run `npm run build` after changing `app.js`.

## Creating a Modal Component

Generate a class-based component and add the trait. Name entry actions `open*Modal`; mark other actions with `#[ModalAction]`; mark one as the default.

```bash
php artisan make:livewire Modals/NoteEdit --class --no-interaction
```

```php
<?php

namespace App\Livewire\Modals;

use App\Models\Note;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Rishadblack\WireModals\Attributes\ModalAction;
use Rishadblack\WireModals\Traits\WithModals;

class NoteEdit extends Component
{
    use WithModals;

    #[Locked]
    public ?int $note_id = null;

    public string $title = '';

    public string $folder = 'inbox';

    /**
     * @param  array{id: int|string|null, data: array<string, string>, action: string|null, component: string, modal: string|null, key: string}  $data
     */
    #[ModalAction(default: true)]
    public function openNoteModal(array $data = []): void
    {
        $this->reset('note_id', 'title', 'folder');
        $this->resetValidation();

        if (isset($data['data']['folder'])) {
            $this->folder = (string) $data['data']['folder'];
        }

        if (! empty($data['id'])) {
            $note = Note::findOrFail($data['id']);

            $this->note_id = $note->id;
            $this->title = $note->title;
            $this->folder = $note->folder;
        }

        $this->modalOpen('NoteModal');
    }

    public function openOptionsModal(): void
    {
        $this->modalOpen('OptionsModal', keepComponent: true);
    }

    #[ModalAction]
    public function noteDelete(array $data = []): void
    {
        $note = Note::findOrFail($data['id']);

        $this->note_id = $note->id;
        $this->title = $note->title;

        $this->modalOpen('DeleteModal');
    }

    public function confirmDelete(): void
    {
        Note::whereKey($this->note_id)->delete();

        $this->dispatch('notes-changed');

        $this->closeComponent();
    }

    public function save(string $mode = 'close'): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'folder' => ['required', 'in:inbox,archive'],
        ]);

        $note = Note::updateOrCreate(['id' => $this->note_id], $validated);

        $this->dispatch('notes-changed');

        if ($mode === 'new') {
            $this->reset('note_id', 'title');

            return;
        }

        $this->note_id = $note->id;

        $this->modalClose('NoteModal');
    }

    public function render(): View
    {
        return view('livewire.modals.note-edit');
    }
}
```

The view declares every modal the component drives. Each `id` must equal the name passed to `modalOpen()`.

```blade
<div>
    <x-wire-modals::modal id="NoteModal" :title="$note_id ? 'Edit note' : 'New note'" size="lg">
        <x-slot:button>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="save('new')">Save &amp; new</button>
            <button type="button" class="btn btn-sm btn-primary" wire:click="save">Save</button>
        </x-slot:button>

        <label class="form-label" for="note-title">Title</label>
        <input id="note-title" type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title">
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror

        <x-wire-modals::trigger component="modals.note-edit" action="openOptionsModal" class="btn btn-link p-0">Options…</x-wire-modals::trigger>
    </x-wire-modals::modal>

    <x-wire-modals::modal id="OptionsModal" title="Options" size="sm" :centered="true">
        <p class="mb-0">Closing this keeps the component alive.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Done</button>
        </x-slot:footer>
    </x-wire-modals::modal>

    <x-wire-modals::modal id="DeleteModal" title="Delete note" size="sm" :centered="true">
        <p class="mb-0">Delete <strong>{{ $title }}</strong>?</p>
        <x-slot:footer>
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-danger" wire:click="confirmDelete">Delete</button>
        </x-slot:footer>
    </x-wire-modals::modal>
</div>
```

Modal props: `id` (required), `title`, `size` (`sm|lg|xl`), `centered`, `scrollable`, `fullscreen` (`true` or a breakpoint), `backdrop` (`true|false|'static'`), `keyboard`, `close-button`. Slots: default body, `button` (header actions), `footer`. Extra attributes merge onto the modal root. The component emits no CSS; use `.wire-modals-body-tall` from the package stylesheet on the body when a tall modal is wanted.

## Triggers

Plain attributes on any element. Nothing in the markup is JavaScript.

```blade
{{-- default action, one data value --}}
<button type="button" class="btn btn-primary" wire:modal="modals.note-edit" data-folder="inbox">Add note</button>

{{-- explicit action and record id --}}
<button type="button" wire:modal="modals.note-edit" wire:modal.action="openNoteModal" wire:modal.id="{{ $note->id }}">Edit</button>

{{-- component@action shorthand --}}
<button type="button" wire:modal="modals.note-edit@noteDelete" wire:modal.id="{{ $note->id }}">Delete</button>

{{-- Blade component: validated at render time --}}
<x-wire-modals::trigger component="modals.note-edit" action="openNoteModal" :record-id="$note->id" :data="['folder' => 'inbox']" class="btn btn-sm btn-outline-secondary">Edit</x-wire-modals::trigger>
```

| Attribute | Meaning |
|---|---|
| `wire:modal` | Component name, optionally `name@action` |
| `wire:modal.action` | Action; falls back to `@action`, then to `#[ModalAction(default: true)]` |
| `wire:modal.id` | Delivered as `$data['id']` |
| `wire:modal.key` | Instance key; different keys mount separate instances |
| `data-*` | Delivered under `$data['data']`, kebab → snake, strings. `data-bs-*` and `data-wire-modals-*` are ignored (config `data_exclusions`) |

For table action columns and other PHP contexts build attributes on the server, never by string concatenation:

```php
use Rishadblack\WireModals\Support\Trigger;

Trigger::make('modals.note-edit', 'openNoteModal')->id($row->getKey())->with('folder', 'inbox')->attributes();
NoteEdit::modalTrigger('noteDelete')->id($row->getKey())->toHtml();
```

While the host request runs the element gets `data-wire-modals-loading="true"`, `aria-busy` and `disabled`; a second click is ignored until it settles.

To open from PHP: `$this->dispatch('wire-modals:load', payload: ['component' => 'modals.note-edit', 'id' => $id])->to('wire-modals.host');`

## Lifecycle Rules

- `modalOpen('X')` shows modal `X`; when `X` closes the component is unmounted.
- `modalOpen('X', keepComponent: true)` shows `X` without tying the component's lifetime to it (sub-modals).
- `modalClose('X')` hides `X`. `closeComponent()` unmounts now; open modals are hidden first so Bootstrap cleans up backdrops.
- A trigger for an already mounted component (same key) **re-enters** it: the action runs on the existing instance. Use this for row buttons and for buttons inside a modal that open another modal of the same component.
- **Chains (one → two → three):** step one is opened with `modalOpen('StepOneModal')` (owns the component), later steps with `modalOpen('StepTwoModal', keepComponent: true)`; "Back" is `modalClose('StepTwoModal')`; "Finish" is `closeComponent()`, which hides every open modal and unmounts. The package raises each new modal and its backdrop above the open ones and keeps `body.modal-open` while any modal remains.
- **Across components:** a trigger inside modal A may name component B. B is mounted on top, reports back with `$this->dispatch('event', ...)->to('a.component-name')` and calls `closeComponent()`; A stays mounted with its modal open.
- One host per layout. It keeps at most `max_mounted` components.

## Actions, Payload and Authorisation

- Callable: public methods matching `action_patterns` (default `open*Modal`) or marked `#[ModalAction]`. Never callable: anything declared by Livewire or the package, lifecycle methods, protected or static methods, names starting with `__`.
- Payload keys: `id`, `data`, `action`, `component`, `modal`, `key`. Treat `id` and `data` as untrusted request input: find records yourself, cast strings, and never use a `data` value as a class or event name without an allow-list.
- Authorise in the action or centrally:

```php
protected function authorizeModalAction(string $action, array $payload): bool
{
    return auth()->user()->can($action === 'noteDelete' ? 'notes.delete' : 'notes.update');
}
```

- Rejections are soft: the request is ignored, a warning is logged (`log_rejections`) and a `wire-modals:rejected` browser event carries `source`, `action` and `reason`.

## Configuration

`config/wire-modals.php`: `host_component`, `action_patterns`, `allowed_components` (restrict to your modal namespaces in production, e.g. `['modals.*']`), `allow_class_names`, `max_mounted`, `max_payload_bytes`, `data_exclusions`, `modal` defaults (`backdrop`, `keyboard`, `size`, `centered`, `scrollable`), `log_rejections`, `legacy`.

## Legacy Mode (migrated applications only)

- `legacy.events`: host also answers `loadComponent` / `closeComponent` Livewire events; modals also react to `modalOpen` / `modalClose` browser events and emit `set-tab`.
- `legacy.directive`: `wire:load:component="{action:'...', component:'...'}"` keeps working (converted to JSON, never evaluated).
- `use WithModals, WithLegacyEvents;` answers `onConfirmAlert`, `onCancelAlert` and `alreadyExistsComponent` with the historical `inputAttributes` wrapping.

Do not introduce these in new code.

## Testing

Feature tests (Pest + Livewire):

```php
Livewire::test('wire-modals.host')
    ->call('load', ['component' => 'modals.note-edit', 'id' => $note->id])
    ->assertSee('Edit note')
    ->assertNotDispatched('wire-modals:rejected');

Livewire::test('modals.note-edit', ['wireModalsPayload' => ['component' => 'modals.note-edit', 'action' => 'noteDelete', 'id' => $note->id]])
    ->assertDispatched('wire-modals:open', modal: 'DeleteModal')
    ->call('confirmDelete')
    ->assertDispatched('wire-modals:dispose', target: 'modals.note-edit');

Livewire::test('modals.note-edit', ['wireModalsPayload' => ['component' => 'modals.note-edit', 'action' => 'render']])
    ->assertDispatched('wire-modals:rejected', action: 'render');
```

Note that `component` is a reserved Livewire dispatch parameter, which is why the package's events use `owner`, `target` and `source`.

Browser tests (Pest browser plugin): click the trigger, `waitForText()` for modal content, `assertVisible('#NoteModal')`, close it, then assert `document.querySelectorAll('[data-wire-modals-entry]').length` is `0`, no `.modal-backdrop` remains and `assertNoJavaScriptErrors()`.

## Troubleshooting

- Console "No host found": add `<livewire:wire-modals.host />` to the layout.
- Console "Bootstrap 5 was not found": import Bootstrap before the package script, or set `window.wireModals = { bootstrap: () => yourBootstrap }`.
- `wire-modals:rejected` in the log: component not registered / without the trait / outside `allowed_components`, or action not callable (add `#[ModalAction]` or rename to `open*Modal`).
- Modal never shows: `id` of `<x-wire-modals::modal>` differs from the `modalOpen()` argument.
- Component vanishes when a secondary modal closes: open it with `keepComponent: true`.
- Published view stopped working: keep `x-data`, `x-on:wire-modals:*`, `wire:ignore.self` on the modal root and the `data-wire-modals-*` attributes on the host root.
