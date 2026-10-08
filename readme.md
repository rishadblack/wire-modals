# Wire Modals

On-demand Livewire components with Bootstrap 5 modals.

A button mounts a Livewire component the first time it is clicked, calls an action on it, keeps it alive while its modal is open, and unmounts it when the modal closes. The component owns its own modal markup, so one component can drive several modals (edit, options, delete confirmation) and a list page never renders a modal it does not need.

```blade
<button class="btn btn-primary" wire:modal="modals.note-edit" wire:modal.id="{{ $note->id }}">Edit</button>
```

```php
class NoteEdit extends Component
{
    use WithModals;

    #[ModalAction(default: true)]
    public function openNoteModal(array $data = []): void
    {
        $this->note = Note::findOrFail($data['id']);

        $this->modalOpen('NoteModal');
    }
}
```

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Your first modal](#your-first-modal)
- [How it works](#how-it-works)
- [Triggers](#triggers)
- [Actions and payload](#actions-and-payload)
- [Re-entry and several modals per component](#re-entry-and-several-modals-per-component)
- [Sub-modals and keeping the component alive](#sub-modals-and-keeping-the-component-alive)
- [Nested modals: one, then two, then three](#nested-modals-one-then-two-then-three)
- [Modals across components](#modals-across-components)
- [Delete with confirmation](#delete-with-confirmation)
- [Authorisation](#authorisation)
- [Configuration reference](#configuration-reference)
- [Browser events](#browser-events)
- [Security notes](#security-notes)
- [Legacy mode](#legacy-mode)
- [Testing](#testing)
- [Customising the markup](#customising-the-markup)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.3 or newer |
| Laravel | 11, 12 or 13 |
| Livewire | 3 or 4 (Alpine is bundled with Livewire) |
| Bootstrap | 5.3, loaded as `window.bootstrap` |

## Installation

**1. Install the package.**

```bash
composer require rishadblack/wire-modals
```

**2. Load the browser script** after Bootstrap and before Livewire starts. With Vite, in `resources/js/app.js`:

```js
import 'bootstrap';
import '../../vendor/rishadblack/wire-modals/resources/js/wire-modals.js';
```

The script registers the `wire:modal` directive and two Alpine components. It uses no other library.

**3. Place the host once in your layout**, anywhere inside `<body>`:

```blade
<livewire:wire-modals.host />
```

**4. Optionally** import the small stylesheet and publish the config. The stylesheet (`resources/css/wire-modals.css`) only adds a `.wire-modals-body-tall` helper for tall scrolling bodies and a progress cursor on triggers while they load; the modal component itself ships no CSS.

```css
/* resources/css/app.css */
@import '../../vendor/rishadblack/wire-modals/resources/css/wire-modals.css';
```

```bash
php artisan vendor:publish --tag=wire-modals-config
```

Rebuild your assets afterwards (`npm run build`).

## Your first modal

Create a Livewire component and add the trait. Actions are public methods named `open*Modal` or marked with the `#[ModalAction]` attribute. They receive one array with the trigger's `id` and `data`.

```php
<?php

namespace App\Livewire\Modals;

use App\Models\Note;
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

    /**
     * @param  array{id: int|string|null, data: array<string, string>}  $data
     */
    #[ModalAction(default: true)]
    public function openNoteModal(array $data = []): void
    {
        $this->reset('note_id', 'title');

        if (! empty($data['id'])) {
            $note = Note::findOrFail($data['id']);

            $this->note_id = $note->id;
            $this->title = $note->title;
        }

        $this->modalOpen('NoteModal');
    }

    public function save(): void
    {
        $this->validate(['title' => ['required', 'string', 'max:120']]);

        Note::updateOrCreate(['id' => $this->note_id], ['title' => $this->title]);

        $this->dispatch('notes-changed');

        $this->modalClose('NoteModal');
    }

    public function render()
    {
        return view('livewire.modals.note-edit');
    }
}
```

The view declares the modal. Its `id` must equal the name passed to `modalOpen()`:

```blade
<div>
    <x-wire-modals::modal id="NoteModal" :title="$note_id ? 'Edit note' : 'New note'" size="lg">
        <x-slot:button>
            <button type="button" class="btn btn-sm btn-primary" wire:click="save">Save</button>
        </x-slot:button>

        <label class="form-label" for="note-title">Title</label>
        <input id="note-title" type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title">
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </x-wire-modals::modal>
</div>
```

Then trigger it from any page:

```blade
<button type="button" class="btn btn-primary" wire:modal="modals.note-edit">New note</button>

<button type="button" class="btn btn-sm btn-outline-secondary" wire:modal="modals.note-edit" wire:modal.id="{{ $note->id }}">Edit</button>
```

`modals.note-edit` is the Livewire name of `App\Livewire\Modals\NoteEdit`.

## How it works

1. A click on a `wire:modal` element builds a payload from the element's attributes (nothing is evaluated) and calls `load()` on the host component.
2. The host checks the payload, the component name and the limits, then mounts the component with the payload. Livewire calls the component's action in the same request.
3. The action fills properties and calls `modalOpen('NoteModal')`, which shows the Bootstrap modal in the browser.
4. When that modal closes, the host unmounts the component. Clicking the trigger again mounts a fresh instance.

Everything the browser sends is validated twice, once by the host and once by the component, and only allow-listed methods can be called. See [Security notes](#security-notes).

## Triggers

A trigger is any element with a `wire:modal` attribute:

| Attribute | Required | Meaning |
|---|---|---|
| `wire:modal="modals.note-edit"` | yes | Component name. `modals.note-edit@noteDelete` names the action too |
| `wire:modal.action="openNoteModal"` | no | Action method. Defaults to the `@action` part, then to the method marked `#[ModalAction(default: true)]` |
| `wire:modal.id="7"` | no | Record key, delivered as `$data['id']` |
| `wire:modal.key="notes-7"` | no | Instance key. Triggers with different keys mount separate instances |
| `data-folder-id="3"` | no | Any `data-*` attribute becomes `$data['data']['folder_id']`. Prefixes in `data_exclusions` (default `data-bs-*`, `data-wire-modals-*`) are skipped |

All values arrive as strings. Keys are lower-cased kebab → snake (`data-order-type-id` → `order_type_id`); keys that are not plain identifiers are dropped.

### Blade component

```blade
<x-wire-modals::trigger component="modals.note-edit" action="openNoteModal" :record-id="$note->id" :data="['folder_id' => 3]" class="btn btn-sm btn-outline-secondary">
    Edit
</x-wire-modals::trigger>
```

Renders a `<button type="button">` by default; `tag="a"` renders a link. Values are validated at render time and escaped by Blade.

### Server-side builder

For table action columns or any PHP context:

```php
use Rishadblack\WireModals\Support\Trigger;

Trigger::make('modals.note-edit', 'openNoteModal')->id($note->id)->with('folder_id', 3)->attributes();
// ['wire:modal' => 'modals.note-edit', 'wire:modal.action' => 'openNoteModal', 'wire:modal.id' => '7', 'data-folder-id' => '3']

NoteEdit::modalTrigger('noteDelete')->id($note->id)->toHtml();
// wire:modal="modals.note-edit" wire:modal.action="noteDelete" wire:modal.id="7"
```

### Loading state

While the host request is pending the element gets `data-wire-modals-loading="true"`, `aria-busy="true"` and (for buttons) `disabled`. A second click during that time is ignored.

### Opening from PHP

Another component can ask the host to load something:

```php
$this->dispatch('wire-modals:load', payload: ['component' => 'modals.note-edit', 'id' => $id])->to('wire-modals.host');
```

## Actions and payload

An action is a public method on the component that is **either** named to match `action_patterns` (default `open*Modal`) **or** marked `#[ModalAction]`. Methods declared by Livewire or by this package are never callable, whatever the config says. Lifecycle methods (`mount`, `render`, `updated*`...) are refused too.

Every action receives one array:

```php
[
    'id'        => '7',                      // wire:modal.id, or null
    'data'      => ['folder_id' => '3'],     // data-* attributes
    'action'    => 'openNoteModal',
    'component' => 'modals.note-edit',
    'modal'     => null,
    'key'       => 'modals.note-edit',
]
```

`id` and `data` are request input. Load records yourself, cast values, and authorise (see below). The package bounds the input: ids up to 191 characters, `data` up to `max_payload_bytes`, three levels deep, 50 values, scalars only.

Mark one method with `#[ModalAction(default: true)]` and triggers can omit the action entirely.

## Re-entry and several modals per component

While a component is mounted, another click on a trigger for the same component (same key) does not mount it again: the host forwards the payload and the action runs on the existing instance. That is how a list page opens the same edit modal for different rows, and how a button inside a modal can open another modal of the same component.

Declare as many `<x-wire-modals::modal>` elements as you need in one view and open each by id.

## Sub-modals and keeping the component alive

Closing a modal opened with `modalOpen('X')` unmounts the component. For a secondary modal that must not end the component (options, pickers, previews) pass `keepComponent`:

```php
public function openOptionsModal(): void
{
    $this->modalOpen('OptionsModal', keepComponent: true);
}
```

`modalClose('X')` hides a modal. `closeComponent()` unmounts the component explicitly; any of its modals still open are hidden first so Bootstrap removes backdrops cleanly.

## Nested modals: one, then two, then three

Modals stack. The package raises each newly shown modal and its backdrop above the ones already open and keeps the page locked while any modal is visible, so a chain of steps in one component is just three modals and a few method calls:

```php
#[ModalAction(default: true)]
public function openStepOneModal(array $data = []): void
{
    $this->modalOpen('StepOneModal');                        // owns the component
}

public function openStepTwoModal(): void
{
    $this->validate(['name' => ['required']]);

    $this->modalOpen('StepTwoModal', keepComponent: true);   // stacks on top of step one
}

public function openStepThreeModal(): void
{
    $this->modalOpen('StepThreeModal', keepComponent: true); // stacks on top of step two
}

public function backToStepTwo(): void
{
    $this->modalClose('StepThreeModal');                     // reveals step two again
}

public function finish(): void
{
    $this->dispatch('wizard-finished', name: $this->name);

    $this->closeComponent();                                 // hides all three, then unmounts
}
```

Inside the modals, "Next" can be a plain `wire:click="openStepTwoModal"` or a trigger for the same component (`<x-wire-modals::trigger component="modals.wizard" action="openStepThreeModal">`): the host re-enters the mounted instance either way.

## Modals across components

A modal can open a modal of **another** component. The second component is mounted on top of the first, does its work, reports back with an ordinary Livewire event and unmounts itself; the first component and its modal stay where they were.

```blade
{{-- inside NoteEdit's modal --}}
<x-wire-modals::trigger component="modals.tag-picker" :data="['for' => 'note']" class="btn btn-sm btn-outline-primary">Pick a tag…</x-wire-modals::trigger>
```

```php
// TagPicker
public function pick(string $tag): void
{
    $this->dispatch('tag-picked', tag: $tag)->to('modals.note-edit');

    $this->closeComponent();
}

// NoteEdit
#[On('tag-picked')]
public function addTag(string $tag): void { /* ... */ }
```

## Delete with confirmation

Use a second modal in the same component. The delete action is not named `open*Modal`, so it carries the attribute:

```php
#[ModalAction]
public function noteDelete(array $data = []): void
{
    $this->note_id = Note::findOrFail($data['id'])->id;

    $this->modalOpen('DeleteModal');
}

public function confirmDelete(): void
{
    Note::whereKey($this->note_id)->delete();

    $this->dispatch('notes-changed');

    $this->closeComponent();
}
```

```blade
<button class="btn btn-sm btn-outline-danger" wire:modal="modals.note-edit@noteDelete" wire:modal.id="{{ $note->id }}">Delete</button>
```

## Authorisation

Override the hook to plug in your permission system. Returning `false` refuses the call, logs it and dispatches `wire-modals:rejected`.

```php
protected function authorizeModalAction(string $action, array $payload): bool
{
    return match ($action) {
        'noteDelete' => auth()->user()->can('notes.delete'),
        default => auth()->user()->can('notes.update'),
    };
}
```

`#[ModalAction(ability: 'notes.delete')]` stores a hint on the method; the package does not interpret it, your hook can read it through reflection or you can simply switch on `$action`.

## Configuration reference

```php
// config/wire-modals.php
return [
    'host_component' => 'wire-modals.host',   // Livewire name of the host
    'action_patterns' => ['open*Modal'],       // callable method names (plus #[ModalAction])
    'allowed_components' => ['*'],             // mountable component names
    'allow_class_names' => false,              // allow fully qualified class names as component
    'max_mounted' => 15,                       // components one host keeps alive
    'max_payload_bytes' => 8192,               // JSON size cap for "data"
    'data_exclusions' => ['bs-', 'wire-modals-'], // data-* prefixes triggers ignore
    'modal' => [
        'backdrop' => 'static',                // true, false or 'static'
        'keyboard' => false,
        'size' => null,                        // null, 'sm', 'lg', 'xl'
        'centered' => false,
        'scrollable' => false,
    ],
    'log_rejections' => true,
    'legacy' => ['events' => false, 'directive' => false],
];
```

`<x-wire-modals::modal>` props: `id` (required), `title`, `size`, `centered`, `scrollable`, `fullscreen` (`true` or a breakpoint such as `md`), `backdrop`, `keyboard`, `close-button`. Slots: default (body), `button` (header actions), `footer`.

## Browser events

All events are plain `window` events; listen with `x-on:wire-modals:opened.window` or `window.addEventListener`.

| Event | Detail | When |
|---|---|---|
| `wire-modals:opened` | `{ modal, owner }` | A modal was shown. `owner` is the component that will be unmounted on close, or `null` |
| `wire-modals:hidden` | `{ modal, owner }` | A lifetime-owning modal closed (the host reacts to it) |
| `wire-modals:rejected` | `{ source, action, reason }` | The host or a component refused a payload |
| `wire-modals:open`, `wire-modals:close`, `wire-modals:dispose` | | Dispatched by the trait; handled by the package |

## Security notes

- The host's component list is a locked Livewire property: the browser cannot add entries.
- Only registered Livewire components that use `WithModals` and match `allowed_components` can be mounted. Class names are refused unless `allow_class_names` is on.
- Only public application methods matching `action_patterns` or marked `#[ModalAction]` can be called. Nothing declared by Livewire or by the package is reachable.
- Trigger values are read as attributes and parsed as JSON; the package never evaluates markup and works under a Content Security Policy that forbids `eval`.
- Payload keys and sizes are validated in the browser and again on the server, so `data-__proto__` or oversized data never reach an action.
- Rejections log the component name, the action and the reason, never the payload data.
- What the package cannot do: authorise your records. Do that in the action or in `authorizeModalAction()`.

## Legacy mode

For applications that migrated from a hand-rolled loader with the historical names (`wire:load:component`, `loadComponent`, `closeComponent`, `modalOpen`, `modalClose`, `onConfirmAlert`, `onCancelAlert`, `set-tab`):

- `legacy.events = true`: the host also answers the `loadComponent` / `closeComponent` Livewire events, modals also react to the `modalOpen` / `modalClose` browser events, and `set-tab { modalId }` is emitted after a modal opens.
- `legacy.directive = true`: `wire:load:component="{action:'...', component:'...', id:'...', data:{...}}"` keeps working. The object literal is converted to JSON and parsed, not evaluated.
- `Rishadblack\WireModals\Traits\WithLegacyEvents` next to `WithModals` answers `onConfirmAlert`, `onCancelAlert` and `alreadyExistsComponent` with the historical `inputAttributes` wrapping.

New code should not use any of this.

## Testing

```bash
composer test
composer lint
composer analyse
```

In your application, test components with Livewire's test helpers:

```php
Livewire::test('wire-modals.host')
    ->call('load', ['component' => 'modals.note-edit', 'id' => $note->id])
    ->assertSee('Edit note');

Livewire::test('modals.note-edit', ['wireModalsPayload' => ['component' => 'modals.note-edit', 'id' => $note->id]])
    ->assertDispatched('wire-modals:open', modal: 'NoteModal', owner: 'modals.note-edit')
    ->call('save')
    ->assertDispatched('wire-modals:close', modal: 'NoteModal');
```

Rejections dispatch `wire-modals:rejected` with `source`, `action` and `reason`.

## Customising the markup

```bash
php artisan vendor:publish --tag=wire-modals-views
```

Keep the `x-data`, `x-on:wire-modals:*` and `wire:ignore.self` attributes on the modal root and the `data-wire-modals-*` attributes on the host root; the browser layer depends on them.

## Troubleshooting

- **Nothing happens on click.** Check the browser console: "No host found" means `<livewire:wire-modals.host />` is missing from the layout; "Bootstrap 5 was not found" means Bootstrap is not on `window.bootstrap`.
- **`wire-modals:rejected` fires.** Read the log: the component is not registered, does not use the trait, is outside `allowed_components`, or the action is not callable. Give the method the `#[ModalAction]` attribute or a matching name.
- **The modal does not show.** The `id` of `<x-wire-modals::modal>` must equal the name passed to `modalOpen()`.
- **The component disappears when a secondary modal closes.** Open it with `modalOpen('X', keepComponent: true)`.
- **Two rows need two instances at once.** Give their triggers different `wire:modal.key` values.

## License

MIT.
