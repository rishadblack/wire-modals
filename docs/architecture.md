# wire-modals — architecture and public API

This is the design to implement. Where it differs from the ERP behaviour described in `how-sylloo-does-it.md`,
the difference is deliberate (usually a security or robustness fix) and the legacy layer bridges the gap.

## Mental model

Three actors talk through namespaced Livewire and browser events:

```
 trigger (button)          host (wire-modals.host)              modal component (uses WithModals)
 ─────────────────         ──────────────────────────            ──────────────────────────────────
 wire:modal='{json}'  ──►  wire-modals:load (Livewire)
                           not mounted → push entry, render  ──►  <livewire:dynamic-component ...>
                                                                  mountWithModals(payload) → runModalAction()
                           mounted     → skipRender(),       ──►  #[On('wire-modals:already-mounted')]
                                         dispatch to component    runModalAction(payload)
                                                                  action → modalOpen('X') → browser event
 browser: wire-modals:open  → bootstrap show, hook hidden once
 browser: wire-modals:close → bootstrap hide
 hidden.bs.modal (component present) → Livewire dispatchTo(host, 'wire-modals:unmount')
 host removes entry → child unmounted
```

The host is the only place that decides what gets mounted. The trait is the only place that decides which
method may run. The browser layer never evaluates code and never decides anything on its own.

## File tree

```
packages/rishadblack/wire-modals/
├── composer.json, LICENSE, license.md, contributing.md, readme.md, changelog.md, .gitignore
├── pint.json, phpstan.neon, phpunit.xml
├── .github/workflows/tests.yml
├── config/wire-modals.php
├── resources/
│   ├── js/wire-modals.js                 # directive + window listeners + bootstrap adapter
│   ├── css/wire-modals.css               # optional .modal-body height helpers (no @push in components)
│   ├── views/
│   │   ├── host.blade.php                # the loader view
│   │   └── components/
│   │       ├── modal.blade.php           # <x-wire-modals::modal>
│   │       └── trigger.blade.php         # <x-wire-modals::trigger>
│   └── boost/
│       ├── guidelines/core.blade.php
│       └── skills/wire-modals-development/SKILL.md
├── src/
│   ├── WireModalsServiceProvider.php
│   ├── Http/Livewire/Host.php
│   ├── Traits/WithModals.php
│   ├── Traits/WithLegacyEvents.php
│   ├── Attributes/ModalAction.php
│   ├── Support/Payload.php
│   ├── Support/ActionResolver.php
│   ├── Support/ComponentResolver.php
│   ├── Support/Trigger.php
│   ├── Exceptions/InvalidPayloadException.php
│   ├── Exceptions/ComponentNotAllowedException.php
│   └── Exceptions/ActionNotAllowedException.php
└── tests/
    ├── TestCase.php, Pest.php
    ├── Feature/*.php
    └── Fixtures/ (components + views used by tests)
```

## Public API

### Trait `Rishadblack\WireModals\Traits\WithModals`

| Member | Behaviour |
|---|---|
| `mountWithModals(array $wireModalsPayload = [])` | Trait mount hook. If the payload is non-empty, calls `runModalAction()`. The parameter name is deliberately unusual so it never collides with a consumer's public property (Livewire assigns unknown mount params to same-named public properties). |
| `#[On('wire-modals:already-mounted')] onWireModalsAlreadyMounted(array $payload)` | Called by the host for re-entry. Runs `runModalAction()`. |
| `runModalAction(array $payload): void` | Normalises through `Payload`, checks `ActionResolver::allows($this, $action)`, then `authorizeModalAction($action, $payload)`, then calls `$this->{$action}($payload)`. Rejections throw inside tests and are logged + ignored (with `skipRender()`) in production, so a tampered payload cannot crash the page. |
| `authorizeModalAction(string $action, array $payload): bool` | Hook, default `true`. Consumers override it to call their permission system. Keep it `protected`. |
| `modalOpen(string $modal, bool $keepComponent = false)` | Dispatches browser event `wire-modals:open` with `['modal' => $modal, 'component' => $keepComponent ? null : $this->getName()]`. Same semantics as the ERP `$onlyModal`. |
| `modalClose(string $modal)` | Dispatches browser event `wire-modals:close` with `['modal' => $modal]`. |
| `closeComponent(?string $component = null)` | Dispatches browser event `wire-modals:dispose` with `['target' => $component ?? $this->getName()]`. The browser hides any shown modal inside that component's root first, then tells the host to unmount (see JS). This fixes the orphaned-backdrop case where the ERP removed the DOM while a modal was open. |
| `static modalTrigger(?string $action = null): Trigger` | A `Support\Trigger` builder pre-filled with the component's Livewire name, for table action columns. |

`modalKeepAlive()` from the first draft was dropped: a component that must survive its modal closing simply opens it
with `keepComponent: true`, and components placed permanently on a page are never in the host's list anyway.

Everything else is `protected`. Do not expose `isValidModalAction`-style helpers publicly.

### Attribute `Rishadblack\WireModals\Attributes\ModalAction`

`#[ModalAction]` on a public method marks it callable through the channel regardless of its name. Optional
argument `ability: string` is passed to `authorizeModalAction()` as a hint (the package does not interpret it).

### Host `Rishadblack\WireModals\Http\Livewire\Host`

| Member | Behaviour |
|---|---|
| `#[Locked] public array $components = []` | Entries keyed by `key` (see Payload). Locked so the browser cannot inject entries. |
| `#[On('wire-modals:load')] load(array $payload)` | Normalise + validate payload. Resolve the alias with `ComponentResolver` (must be a registered Livewire component, must use `WithModals`, must match `allowed_components`). If an entry with the same `key` exists → `skipRender()` and `dispatch('wire-modals:already-mounted', payload: ...)->to($alias)`. Else, if `count($components) >= max_mounted` → log + `dispatch('wire-modals:rejected', ...)` browser event; else push and render. |
| `#[On('wire-modals:unmount')] unmount(string $target)` | Removes every entry of that alias. `skipRender()` when nothing changed. Called by the host's Alpine component as `$wire.unmount(owner)`. `load()` is likewise called as `$wire.load(payload)` by the trigger directive, so the browser never needs the host's name. |
| `getListeners(): array` | Merges the two namespaced listeners with `loadComponent` and `closeComponent` when `config('wire-modals.legacy.events')` is true. Legacy `loadComponent($action, $component, $modal = null, $id = null, $data = [])` keeps the ERP signature (named params from `event.detail`) and forwards to `load()`. |
| `render()` | `wire-modals::host`. Each entry: `<div wire:key="wire-modals-{key}" data-wire-modals-entry="{alias}"><livewire:dynamic-component :is="$alias" :key="'wire-modals-'.$key" :wireModalsPayload="$payload" /></div>`. No `id="modalContent..."` wrapper ids. |

### `Support\Payload`

Static `fromClient(array $raw): array`. Accepts both the new JSON shape and the legacy shape; returns the
canonical array actions receive (same keys as the ERP: `id`, `data`, `action`, `component`, `modal`, plus `key`):

- `component`: required string, max 191 chars, regex `^[A-Za-z0-9][A-Za-z0-9_.:\-]*$` (allows `product::modal.unit-edit`,
  `modals.note-edit`). Backslashes rejected unless `allow_class_names` is true.
- `action`: required string, regex `^[a-zA-Z_][a-zA-Z0-9_]*$`, max 100 chars, never starting with `__`.
- `id`: scalar or null (string ≤ 191, int, or null). Arrays rejected.
- `data`: array, max depth 3, max 50 leaves, scalars only, total JSON ≤ `max_payload_bytes`. Missing → `[]`.
- `modal`: optional string ≤ 100.
- `key`: optional string ≤ 64, regex `^[A-Za-z0-9_\-]+$`; defaults to `component`. The host dedupes on `key`, so
  two instances of one component can coexist when a trigger asks for a different key.
- Unknown keys dropped. Violations throw `InvalidPayloadException`.

### `Support\ActionResolver`

`allows(object $component, string $action): bool` is true only when all of these hold:

1. The method exists, is `public`, not static, not abstract, not a magic method.
2. The declaring class is not `Livewire\Component`, not a Livewire-internal trait, and not `WithModals` /
   `WithLegacyEvents` themselves (so `__dispatch`, `render`, `mount`, `runModalAction`, `closeComponent` and friends
   are never reachable as actions). Implement by walking `ReflectionMethod::getDeclaringClass()` and checking the
   namespace prefix `Livewire\` plus the package's own trait list.
3. The method either carries `#[ModalAction]` or its name matches one of `config('wire-modals.action_patterns')`
   (glob-style, `*` wildcard; default `['open*Modal']`).

### `Support\ComponentResolver`

`classFor(string $alias): ?string` using Livewire's registry without instantiating. `assertAllowed(string $alias)`
throws `ComponentNotAllowedException` unless the class exists, uses `WithModals` (via `class_uses_recursive`),
and the alias matches one pattern in `allowed_components` (default `['*']`).

### `Support\Trigger` (PHP attribute builder)

Fluent, used by Blade components and by table builders such as PowerGrid:

```
Trigger::make('modals.note-edit', 'openNoteModal')->id($note->id)->data(['folder' => 3])->key('notes-2')
    ->toAttribute();      // => wire:modal="{&quot;component&quot;:...}"   (array of attribute => value for Blade merge)
    ->toJson();           // JSON with JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP
```

`toAttributes(): array` returns `['wire:modal' => $json]` (or `'wire:load:component'` when legacy directive mode is
on and the caller asks for it with `->legacy()`). `Trigger` is `Htmlable` so `{{ $trigger }}` renders the attribute
pair escaped. Validate the same way `Payload` does, at build time, so bad values fail in development.

### Blade components

- `<x-wire-modals::modal id="NoteModal" size="lg" position="top" :static="true" :scrollable="false">` with slots
  `title`, `button` (header actions), default slot (body), `footer`. Renders a Bootstrap 5 `.modal` with
  `wire:ignore.self`, `data-bs-backdrop="static"` (configurable), `aria-labelledby`, a close button, and nothing
  else. No `@push('css')`; no theme buttons. Attributes are merged onto the `.modal` root.
- `<x-wire-modals::trigger component="modals.note-edit" action="openNoteModal" :record-id="$note->id" :data="[...]" key="..." tag="button" class="btn btn-primary">Edit</x-wire-modals::trigger>`
  renders `<button type="button" class="..." wire:modal="{json}">slot</button>`. `tag` may be `a` or `button`.

### Trigger markup (final form)

The trigger is plain attributes. Nothing in the markup is JSON or JavaScript:

```blade
<button wire:modal="order::modal.order-edit"
        wire:modal.action="openOrderModal"
        wire:modal.id="{{ $order->id }}"
        wire:modal.key="orders-2"
        data-order-type-id="{{ $this->orderType->id }}">
```

| Attribute | Required | Meaning |
|---|---|---|
| `wire:modal` | yes | Component alias, or `alias@action` shorthand |
| `wire:modal.action` | no | Action method. Falls back to the `@action` part, then to the component's `#[ModalAction(default: true)]` method |
| `wire:modal.id` | no | Record key, sent as `id` |
| `wire:modal.key` | no | Instance key for the host (defaults to the alias) |
| `data-*` | no | Every `data-*` attribute except excluded prefixes becomes a `data` entry. `data-order-type-id` → `order_type_id` |

Rules the browser layer enforces: only the attribute **without modifiers** binds the click (Livewire calls a
directive once per matching attribute, so `wire:modal.action` and `wire:modal.id` must be ignored by the handler);
attributes are read at click time, not at bind time; the payload object is created with a null prototype; data
keys are derived from the attribute name (not `dataset`), lower-cased kebab → snake, and must match
`^[a-z][a-z0-9_]*$` (so `data-__proto__` and the like are dropped); excluded prefixes default to `data-bs-` and
`data-wire-modals-` and can be extended through config; a per-element pending flag blocks a second click until the
first load settles; all values are strings and the docs say so.

### Browser layer (`resources/js/wire-modals.js`)

Load it after Livewire is available (`import '../../vendor/rishadblack/wire-modals/resources/js/wire-modals.js'`
in the app's `app.js`, like wire-tomselect). Alpine (bundled with Livewire) is the only framework used; no jQuery,
no hand-rolled window plumbing where an Alpine component or `$wire` call does the job. Split the source into small
modules under `resources/js/src/` (`trigger.js`, `modal.js`, `host.js`, `payload.js`, `legacy.js`, `bootstrap.js`)
with `wire-modals.js` as the entry that registers everything on `livewire:init` / `alpine:init`.

1. **Trigger** — `Livewire.directive('modal', ...)`, binding only when `directive.modifiers` is empty. On click:
   build the payload from the attributes (see above), locate the host root (`[data-wire-modals-host]`), and call
   `Livewire.find(hostWireId).load(payload)` (the `$wire` proxy returns a promise). Set
   `data-wire-modals-loading="true"` and `disabled` while pending; clear them when the promise settles or after a
   10 s timeout. Never leave a button disabled forever. Clean up with the directive `cleanup`.
2. **Modal** — `Alpine.data('wireModalsModal', ...)` used by `<x-wire-modals::modal>`. Listens on `window` for
   `wire-modals:open` and `wire-modals:close` and acts only when `detail.modal === $el.id` (handle both
   `detail.modal` and `detail[0].modal`, Livewire wraps payloads). Shows/hides through
   `bootstrap.Modal.getOrCreateInstance($el)`. Remembers the `component` it was opened for; on Bootstrap's
   `hidden.bs.modal` (bound once in `init()`, removed in `destroy()`) it dispatches browser event
   `wire-modals:hidden {modal, component}` when a component was recorded, then clears it. After show it dispatches
   `wire-modals:opened {modal, component}` (replaces the ERP `set-tab`).
3. **Host** — `Alpine.data('wireModalsHost', ...)` on the host root. Listens on `window` for `wire-modals:hidden`
   → `$wire.unmount(component)`; and `wire-modals:dispose {component}` → finds its entry
   `[data-wire-modals-entry="component"]`, hides every `.modal.show` inside it and lets their `hidden` events drive
   the unmount; if none are open it calls `$wire.unmount(component)` at once. This guarantees Bootstrap cleans up
   backdrops and `body.modal-open` before Livewire removes the DOM.
4. **Bootstrap accessor** — `bootstrap.js` exports a getter that reads `window.bootstrap` unless
   `window.wireModals.bootstrap` was set before load. Fail loudly once if `Modal` is missing.
5. **Legacy** (`legacy.js`), enabled by `data-wire-modals-legacy-directive` / `-events` attributes rendered on the
   host root from config: `directive` → also register `Livewire.directive('load:component')` that converts the old
   JS object literal to JSON by a conservative normalisation (quote bare keys, single → double quotes) and
   `JSON.parse`s it; on failure log an error naming the element; never `eval`. `events` → the modal component also
   reacts to `modalOpen` / `modalClose` window events, and after open dispatches `set-tab {modalId}`.
6. Nothing else: no `print`, no `callEventFunc`, no SweetAlert. Those stay in the ERP.

### Config `config/wire-modals.php`

```
return [
    'host_component'    => 'wire-modals.host',
    'action_patterns'   => ['open*Modal'],
    'allowed_components'=> ['*'],
    'allow_class_names' => false,
    'max_mounted'       => 15,
    'max_payload_bytes' => 8192,
    'modal' => [
        'backdrop' => 'static',   // true|false|'static'
        'keyboard' => false,
        'position' => 'top',      // Bootstrap modal-dialog-{position}; null for none
    ],
    'log_rejections'    => true,  // Log::warning with alias + action + reason, never the payload data
    'legacy' => [
        'events'    => false,     // host listens to loadComponent / closeComponent; JS listens to modalOpen / modalClose and emits set-tab
        'directive' => false,     // JS registers wire:load:component
    ],
];
```

### Service provider

Mirror `WireTomselectServiceProvider` + the Livewire/Blade registration from `IReportsServiceProvider`:
`mergeConfigFrom`, `loadViewsFrom(..., 'wire-modals')`, `Blade::componentNamespace('Rishadblack\\WireModals\\View\\Components', 'wire-modals')`,
`Livewire::component(config('wire-modals.host_component'), Host::class)`, publishes for config
(`wire-modals-config`) and views (`wire-modals-views`). No routes, no migrations, no commands.

## Event name table

| Purpose | Kind | Name | Payload | Legacy name |
|---|---|---|---|---|
| Trigger → host | Livewire (to host) | `wire-modals:load` | `{payload: {...}}` | `loadComponent` (positional/named params) |
| Host → component (re-entry) | Livewire (to alias) | `wire-modals:already-mounted` | `{payload: {...}}` | `alreadyExistsComponent` (not needed: host is package code) |
| Browser → host | Livewire (to host) | `wire-modals:unmount` | `{component}` | `closeComponent` |
| Component → browser | browser | `wire-modals:open` | `{modal, owner\|null}` | `modalOpen` |
| Component → browser | browser | `wire-modals:close` | `{modal}` | `modalClose` |
| Component → browser | browser | `wire-modals:dispose` | `{target}` | — |
| Browser → page | browser | `wire-modals:opened` | `{modal, owner}` | `set-tab {modalId}` |
| Browser → host | browser | `wire-modals:hidden` | `{modal, owner}` | — |
| Browser → page | browser | `wire-modals:rejected` | `{source, action, reason}` | — |

Note: `component` (like `self`, `to`, `ref`, `el`) is a reserved named parameter of Livewire's `dispatch()`: Livewire 4
treats it as the event target. That is why the package events say `owner`, `target` and `source`.
| SweetAlert answer → component | Livewire | handled by `WithLegacyEvents` | ERP shape | `onConfirmAlert`, `onCancelAlert` |

### `Traits\WithLegacyEvents` (Sylloo only)

`#[On('onConfirmAlert')] onConfirmAlert(array $data = [])` unwraps `inputAttributes` and calls `runModalAction()`.
`#[On('onCancelAlert')] onCancelAlert(array $data = [])` does `skipRender()` and `closeComponent($data['component'] ?? null)`
after unwrapping. The ERP will declare `trait WithDynamicComponent { use WithModals, WithLegacyEvents; }` so its
107 classes need no edits. Document in the trait docblock that it exists only for that migration.

## Test matrix (Pest, Testbench, inside the package)

Fixtures: `NoteEdit` (uses `WithModals`, methods `openNoteModal`, `#[ModalAction] noteDelete`, `notAnAction`,
sub-modal method using `keepComponent`), `PermanentWidget` (`modalKeepAlive()` true), `PlainComponent` (no trait),
Blade views using `<x-wire-modals::modal>`.

| Test file | Covers |
|---|---|
| `ServiceProviderTest` | config merged, host registered, Blade namespace, publishes |
| `HostTest` | load mounts entry; second load same key → `skipRender` + dispatch to alias; different key → second entry; unmount removes; keep-alive not removed; `max_mounted` rejection; `$components` is Locked (`$wire.set` throws); unknown alias, component without trait, alias not in `allowed_components` → rejected + logged, nothing mounted |
| `WithModalsTest` | mount hook runs action; already-mounted listener runs action; `modalOpen` dispatches `wire-modals:open` with/without component; `modalClose`; `closeComponent` default and explicit; `authorizeModalAction` false blocks the call; payload reaches action with ERP keys (`id`, `data`, `component`, `action`) |
| `ActionResolverTest` | allows `open*Modal`; allows `#[ModalAction]`; denies `render`, `mount`, `__dispatch`, `dispatch`, `runModalAction`, `closeComponent`, protected methods, static methods, names starting with `__`; pattern config honoured |
| `PayloadTest` | every validation rule above, both shapes, size/depth limits, unknown keys dropped |
| `TriggerTest` | JSON escaping of `<`, `>`, `"`, `'`, `&`; invalid component/action throws; Blade component renders the attribute; `tag` option |
| `ModalComponentTest` | renders id, size, position, static backdrop, slots, merged attributes; no `<style>` output |
| `LegacyEventsTest` | with `legacy.events` on: `loadComponent` named params and `closeComponent` work on host; off: they are not listeners. `onConfirmAlert` unwraps `inputAttributes`; `onCancelAlert` disposes |
| `LivewireCompatTest` | trait hook and `getListeners()` behave the same on the installed Livewire major (the CI matrix runs 3 and 4) |

## Browser scenarios (Pest browser tests in the sandbox app, `tests/Browser/WireModalsTest.php`)

1. Click "Add note" → modal visible, host has one entry, `assertNoJavaScriptErrors`.
2. Close with the header button → modal hidden, host entry removed, no `.modal-backdrop` left, `body` has no `modal-open`.
3. Click "Edit" on row 1, close, click "Edit" on row 2 → fields show row 2 data (re-entry path), still one entry.
4. Open the nested sub-modal (`keepComponent`), close it → parent modal still open, entry still present.
5. Save and close → list refreshed, entry removed.
6. Trigger disabled while loading and re-enabled after open.
7. A tampered trigger (`action: "render"`) does nothing, no JS error, console has the warning only if configured.
8. With legacy flags on (a second playground route or config toggle): a `wire:load:component` button with the ERP
   object-literal syntax still opens the modal.

## Later: ERP migration checklist (separate task, after 1.0.0 is tagged)

1. `composer require rishadblack/wire-modals` in sylloo-erp.
2. `config/wire-modals.php`: `legacy.events = true`, `legacy.directive = true`, `action_patterns` built from the
   grep in `how-sylloo-does-it.md`, `allowed_components => ['*::modal.*', '*::settings.modal.*', ...]` from the
   same grep on `component:` values.
3. `Modules/Core/app/Traits/WithDynamicComponent.php` → `trait WithDynamicComponent { use WithModals, WithLegacyEvents; }`
   and nothing else. Delete `DynamicComponentLoader.php` and its view.
4. Layouts: replace `<livewire:core::dynamic-component-loader />` with `<livewire:wire-modals.host />`.
5. `resources/js/app.js`: delete the `modalOpen`, `modalClose`, `callEventFunc`, `.callEvent` and `load:component`
   blocks; import the package script. Keep `print`.
6. `x-theme::backend.tab`: listen to `wire-modals:opened` (or keep `set-tab` while `legacy.events` is on).
7. `WithRowActions`: build attributes with `Trigger::make(...)->legacy()->toAttributes()` so ids are JSON-escaped.
8. `x-theme::backend.modal`: either keep it, or make it a thin wrapper around `<x-wire-modals::modal>` and move its
   `<style>` to `_theme.scss`.
9. Add `authorizeModalAction()` to `LivewireComponent` calling `ucan()` where modals currently check permissions
   inside the action; then remove the per-method checks over time.
10. Replace the ERP skill `.ai/skills/modal-development/SKILL.md` content with a pointer to the package skill plus
    the ERP-specific conventions (`lang()`, `ucan()`, `refreshDatatable`).
11. Switch each module from legacy to `wire:modal` triggers and `#[ModalAction]` on delete methods, then turn the
    legacy flags off.
