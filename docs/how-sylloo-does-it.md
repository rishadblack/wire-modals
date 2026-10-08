# How the dynamic component system works in Sylloo ERP today

Reference codebase: `C:\Office\sylloo-project\sylloo-erp` (Laravel 13, PHP 8.4, Livewire 4.4,
`nwidart/laravel-modules` 13, `mhmiton/laravel-modules-livewire` 7, PowerGrid 6, `jantinnerezo/livewire-alert` 4,
Bootstrap 5). Component aliases look like `product::modal.unit-edit` because of the modules-livewire naming.

Read this before designing. The ERP has 107 classes using the trait, 7 modal views that trigger other modals
from inside a modal, 6 calls that keep the component alive after a modal closes, and two pages that dispatch the
load event from hand-written JavaScript. All of that must keep working through the package's legacy layer.

## The six parts

| # | Part | File | Role |
|---|---|---|---|
| 1 | Consumer trait | `Modules/Core/app/Traits/WithDynamicComponent.php` | Added to every modal component. `modalOpen`, `modalClose`, `closeComponent`, and the listeners `alreadyExistsComponent`, `onConfirmAlert`, `onCancelAlert`. |
| 2 | Host (loader) | `Modules/Core/app/Pages/DynamicComponentLoader.php` + `Modules/Core/resources/views/pages/dynamic-component-loader.blade.php` | One instance per layout (`<livewire:core::dynamic-component-loader />` in `Modules/Theme/resources/views/layouts/backend.blade.php:322` and `developer.blade.php:248`). Keeps `public $components = []`, mounts each entry with `<livewire:dynamic-component :is="..." :key="..." :$data />`. |
| 3 | Browser layer | `resources/js/app.js` | `Livewire.directive("load:component")`, window listeners `modalOpen` and `modalClose`, Bootstrap `hidden.bs.modal` hook that dispatches `closeComponent`, and the `set-tab` reset event. |
| 4 | Confirm round-trip | `Modules/Core/app/Traits/WithSweetAlert.php` (`alertConfirm`, `alert`, `dispatchAlertEvent`) | Delete flows. Bakes the trigger payload into the SweetAlert so the answer comes back to the same component as `onConfirmAlert` or `onCancelAlert`. |
| 5 | Modal markup | `Modules/Theme/resources/views/components/backend/modal.blade.php` (`x-theme::backend.modal`) | Bootstrap 5 `.modal` with `wire:ignore.self`, static backdrop, `id` = the name passed to `modalOpen()`. Pushes a `<style>` block per instance. |
| 6 | Trigger producers | `Modules/Core/app/Traits/PowerGrid/WithRowActions.php`, `Modules/Theme/resources/views/components/input/media.blade.php`, `Modules/Theme/resources/views/components/item-input/serial.blade.php`, every `*-list.blade.php` "Add" button | Emit `wire:load:component="{action:'openUnitModal', component:'product::modal.unit-edit', id:'...', data:{...}}"` as a raw JS object literal. |

Base class: every page and modal extends `Modules\Core\Http\Common\LivewireComponent`, which only adds
`WithSweetAlert` and a `validate()` override that shows errors in an alert.

## Request-by-request flow

### A. First click on a trigger (component not mounted yet)

1. Blade renders a button with `wire:load:component="{action:'openUnitModal', component:'product::modal.unit-edit'}"`
   (`Modules/Product/resources/views/pages/unit-list.blade.php:8`).
2. The directive in `app.js` parses the expression with `new Function("return " + expression)()`, sets
   `data-loading="true"`, disables the button, and dispatches a **DOM** `CustomEvent("loadComponent", {detail, bubbles:true})`.
3. Livewire registers every `#[On('x')]` listener as a `window.addEventListener('x', ...)` as well (confirmed in
   `vendor/livewire/livewire/dist/livewire.esm.js`, look for `receivedBy.push`). So the bubbling DOM event reaches
   `DynamicComponentLoader::loadComponent($action, $component, $modal = null, $id = null, $data = [])`, with
   `event.detail` spread into the named parameters. This bridge is implicit and undocumented.
4. The loader pushes `{id, data, action, component, modal}` onto `$components` and re-renders. The view mounts
   `<livewire:dynamic-component :is="$data['component']" :key="$data['action'].'Component'" :$data />`
   inside `<div id="modalContent{{ $data['action'] }}" wire:key="modal-component-list-{{ $data['action'] }}">`.
5. The child mounts. Livewire calls the trait hook `mountWithDynamicComponent(array $data = [])` with the `data`
   mount param (`SupportLifecycleHooks::callTraitHook('mount', $params)`). The hook calls
   `alreadyExistsComponent($data)`, which:
   - if `$data['data']['extra'][2]` exists, explodes it on `:` and does `$this->{$key} = $value` (unused by any
     consumer; dangerous, see security doc);
   - if `$data['action']` names an existing method, calls `$this->{$data['action']}($data)`.
6. The action (`openUnitModal($data)`) checks `ucan()`, loads the record from `$data['id']`, fills properties and
   calls `$this->modalOpen('UnitModal')`.
7. `modalOpen` dispatches the Livewire event `modalOpen` with `['modal' => 'UnitModal', 'component' => $this->getName()]`
   (`component` omitted when `$onlyModal` is true).
8. In the browser the `modalOpen` window listener does `new bootstrap.Modal(document.getElementById(modal)).show()`,
   and if `component` is present adds a `hidden.bs.modal` listener that will `Livewire.dispatch("closeComponent", {component})`
   and fires `set-tab` so `x-theme::backend.tab` (`tab.blade.php`) resets to its default tab for this modal id.
9. The trigger button is re-enabled by a one-shot `Livewire.interceptRequest(... onSuccess ...)` after the next
   successful request.

### B. Click again while the component is still mounted

1. Same directive, same DOM event, same `loadComponent` on the loader.
2. `collect($this->components)->contains('component', $component)` is true, so the loader dispatches
   `alreadyExistsComponent` **to** the component (`->to($component)`) with the same payload shape and returns
   (without `skipRender()`, so the host re-renders for nothing).
3. The component's `#[On('alreadyExistsComponent')]` runs the same action again (step A5–A8). This is what lets a
   list page open the same edit modal for different rows without re-mounting.

### C. Saving and closing

- `storeUnit($storeType)` saves, then either resets for "save and new", keeps the id for "save", or calls
  `modalClose('UnitModal')` which dispatches `modalClose` → browser `bootstrap.Modal.getInstance(el).hide()`.
- Bootstrap fires `hidden.bs.modal` → the listener from A8 dispatches Livewire `closeComponent {component}`.
- `DynamicComponentLoader::closeComponent($component)` removes the entry → the child is unmounted on re-render.
- Every modal also dispatches `refreshDatatable`, handled by `Modules/Core/app/Traits/PowerGrid/WithTableEvents.php`.
  That event is **not** part of the package; it stays in the ERP.

### D. Delete with confirmation (no modal involved)

1. Row button: `wire:load:component="{action:'unitDelete', component:'product::modal.unit-edit', id:'...'}"`.
2. Steps A1–A5 mount the component (or B re-enters it) and call `unitDelete($data)`.
3. `unitDelete` calls `$this->skipRender()` then `$this->alertConfirm($data, 'Are you sure...')`.
   `alertConfirm` sees no `isConfirmed` key, shows the SweetAlert with
   `onConfirmed => ['component' => $data['component'], 'listener' => 'onConfirmAlert']`,
   `onDismissed => 'onCancelAlert'`, `inputAttributes => $data + ['isConfirmed' => true]`, and returns `false`.
4. The user answers. `WithSweetAlert::dispatchAlertEvent` forwards to `onConfirmAlert` on that component (or
   `onCancelAlert` globally). The trait's `onConfirmAlert` unwraps `$data['inputAttributes']` and calls
   `alreadyExistsComponent($data)` → `unitDelete($data)` again, now with `isConfirmed`, so `alertConfirm`
   returns the data and the delete runs.
5. `unitDelete` then dispatches `refreshDatatable` and `closeComponent()` (no argument → `$this->getName()`),
   which unmounts the component. `onCancelAlert` does the same unmount after `skipRender()`.

### E. Sub-modals inside a mounted component

`Modules/Order/app/Pages/Modal/Traits/WithSerialBatchSqFt.php:37-43` and `Modules/Common/app/Pages/Modal/MediaFileModal.php:278-353`
call `modalOpen('SerialModal', true)`. With `$onlyModal = true` the payload carries no `component`, so the
browser does not attach the unmount hook: closing the sub-modal leaves the parent component (and its main modal)
alive. `ManageItemQuantity.php` and `TemplateSetting.php` use `modalOpen` from a non-modal page component for the
same reason: the component is already on the page, it just needs the modal shown.

### F. Payload `data` as a side channel

`media.blade.php:23-45` sends `data:{listener:'...', perameter:'...', remove:'true'}`. `MediaFileModal::openMediaFileModal`
reads `$data['data']['listener']`, stores it, and later dispatches that listener name back with the chosen file.
`serial.blade.php` and `account-receipt-list.blade.php` send record ids in `data`. So `data` is an arbitrary
array the action interprets; the package must pass it through unchanged (after validation) under the same key.

### G. Hand-written triggers

`Modules/Website/resources/views/pages/template-market.blade.php:31` and `template-preview-edit.blade.php:46` call
`Livewire.dispatch('loadComponent', {action, component})` directly from a `postMessage` handler. The package's
legacy layer must therefore accept the Livewire event name `loadComponent` on the host, not only the directive.

## Payload shape the consumers rely on

Actions receive exactly one array argument:

```
[
  'id'        => scalar|null,      // record key, from the trigger
  'data'      => array,            // free-form, from the trigger `data:{}`
  'action'    => string,           // the method being called
  'component' => string,           // the alias of the component itself
  'modal'     => string|null,      // rarely set; unused by consumers
  // plus 'isConfirmed' => true on the confirm round-trip
]
```

Consumers index `$data['id']` and `$data['data'][...]`. Keep those keys and their nesting.

## Things that exist but are unused (do not carry over)

- `isValidModalAction()` and `extractModalName()` in the trait: written as a guard, never called.
- The `$data['data']['extra'][2]` property write: no view or class sends `extra`.
- The `.callEvent` jQuery handler in `app.js`: zero usages.
- `onConfirmValidationAlert`: referenced by `LivewireComponent::validate()`, implemented nowhere.
- `console.log(event.detail)` in the `modalClose` listener.

## Numbers that matter for the migration

| What | Count | How to reproduce |
|---|---|---|
| Classes using the trait | 107 | `grep -rl "use WithDynamicComponent" Modules --include=*.php` |
| `modalOpen(..., true)` keep-alive calls | 6 | `grep -rnE "modal(Open\|Close)\([^)]*,\s*true" Modules --include=*.php` |
| Modal views that trigger other components | 7 | `grep -rl "load:component" Modules/*/resources/views/pages/modal Modules/*/resources/views/pages/settings/modal` |
| Direct `Livewire.dispatch('loadComponent')` | 2 | `grep -rn "Livewire.dispatch('loadComponent'" Modules` |
| Distinct action names called through the channel | run it | `grep -rhoE "action:\s*'[A-Za-z]+'" Modules --include=*.blade.php --include=*.php \| sort \| uniq -c \| sort -rn` |

The last command is the input for the ERP's `action_patterns` config when it adopts the package: the names are
mostly `open*Modal`, `*Delete`, plus a handful of others (`chartOfAccountDelete`, `addModal`, ...).
