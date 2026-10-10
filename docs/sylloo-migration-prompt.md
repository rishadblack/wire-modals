# Migrate Sylloo ERP to rishadblack/wire-modals and remove the hand-rolled dynamic component system

You are working in the Sylloo ERP repository (`C:\Office\sylloo-project\sylloo-erp`, Laravel 13, PHP 8.4,
Livewire 4.4, nwidart modules, PowerGrid 6, Bootstrap 5, `jantinnerezo/livewire-alert` 4). Follow the repository's
`AGENTS.md`, `.ai/guidelines/sylloo-erp.md` and the module skills. Two ERP rules override everything else:

1. **Never write, edit, create or run tests on your own.** Finish the code, then ask: "Want me to write/run tests
   for this?" and wait for a yes.
2. Match sibling files before writing new code; use `lang()`, `ucan()`, `$this->alert()`; never introduce
   alternatives.

## Goal

Replace the ERP's own "dynamic component / modal" system with the package `rishadblack/wire-modals` 1.0.0
(https://github.com/rishadblack/wire-modals, readme and the `wire-modals-development` Boost skill are the API
reference), then delete everything the ERP no longer needs. The 107 modal classes that `use WithDynamicComponent`
must keep working **without edits** in phase 1; their triggers (`wire:load:component`), the SweetAlert confirm
round-trip (`onConfirmAlert` / `onCancelAlert`) and `refreshDatatable` must behave exactly as today.

The system being replaced consists of these six parts (read each before touching it):

| Part | File | Fate |
|---|---|---|
| Consumer trait | `Modules/Core/app/Traits/WithDynamicComponent.php` | becomes a 3-line alias of the package traits |
| Host / loader | `Modules/Core/app/Pages/DynamicComponentLoader.php` + `Modules/Core/resources/views/pages/dynamic-component-loader.blade.php` | deleted, replaced by `<livewire:wire-modals.host />` |
| Browser layer | `resources/js/app.js`: `modalOpen` / `modalClose` window listeners, `callEventFunc`, the jQuery `.callEvent` handler, `Livewire.directive("load:component")` | deleted, replaced by the package script (keep the `print` listener) |
| Confirm round-trip | `Modules/Core/app/Traits/WithSweetAlert.php` | kept unchanged; the package's `WithLegacyEvents` answers its events |
| Modal markup | `Modules/Theme/resources/views/components/backend/modal.blade.php` (`x-theme::backend.modal`) | rewritten as a thin wrapper around `<x-wire-modals::modal>` |
| Trigger producers | `Modules/Core/app/Traits/PowerGrid/WithRowActions.php`, `Modules/Theme/resources/views/components/input/media.blade.php`, `Modules/Theme/resources/views/components/item-input/serial.blade.php`, every `*-list.blade.php` "Add" button, `Modules/Website/resources/views/pages/template-market.blade.php:31` and `template-preview-edit.blade.php:46` (`Livewire.dispatch('loadComponent', ...)`) | unchanged in phase 1 (legacy mode), modernised in phase 3 |

Layouts that render the old loader: `Modules/Theme/resources/views/layouts/backend.blade.php` (around line 322) and
`Modules/Theme/resources/views/layouts/developer.blade.php` (around line 248). The tab component
`Modules/Theme/resources/views/components/backend/tab.blade.php` listens for the `set-tab` browser event.

## Phase 0 — Inventory (no edits)

Run and keep the output; it drives the config in phase 1:

```bash
git checkout -b feature/wire-modals
grep -rhoE "action:\s*'[A-Za-z]+'" Modules --include=*.blade.php --include=*.php | sort | uniq -c | sort -rn
grep -rhoE "component:\s*'[a-z0-9:.\-]+'" Modules --include=*.blade.php --include=*.php | sort -u
grep -rn "Livewire.dispatch('loadComponent'" Modules
grep -rnE "modal(Open|Close)\([^)]*,\s*true" Modules --include=*.php
grep -rn "onlyModal:" Modules --include=*.php
grep -rl "use WithDynamicComponent" Modules --include=*.php | wc -l
grep -rn "window.bootstrap" resources/js Modules/Theme/resources
```

From the first two commands build:

- `action_patterns`: `open*Modal` plus a pattern or exact name for every other action in the list (typically
  `*Delete`, `*Import`, `addModal`, `*Clone`). Prefer exact names over `*`. Any action that cannot be expressed as a
  safe pattern gets `#[Rishadblack\WireModals\Attributes\ModalAction]` on its method instead (that is the only
  edit allowed inside consumer classes in phase 1).
- `allowed_components`: patterns covering every alias seen, for example `*::modal.*`, `*::settings.modal.*`,
  `*::templates.modal.*`, plus any page-level components that are loaded this way.

Confirm `window.bootstrap` exists in the browser bundle (the old `app.js` already calls `window.bootstrap.Modal`,
so it should). If it does not, add `import * as bootstrap from 'bootstrap'; window.bootstrap = bootstrap;` before
the package import.

## Phase 1 — Zero-change switch (every consumer keeps working)

1. **Install.** Until the package is on Packagist, add a VCS repository to `composer.json`
   (`{"type": "vcs", "url": "https://github.com/rishadblack/wire-modals"}`) and run
   `composer require rishadblack/wire-modals:^1.0`. Then `php artisan vendor:publish --tag=wire-modals-config`.
2. **Configure `config/wire-modals.php`:**
   - `legacy.events => true`, `legacy.directive => true`
   - `action_patterns` and `allowed_components` from phase 0
   - `max_mounted => 25`
   - leave `allow_class_names => false`, keep `log_rejections => true`
3. **Alias the trait.** Replace the whole body of `Modules/Core/app/Traits/WithDynamicComponent.php` with:

   ```php
   <?php

   namespace Modules\Core\Traits;

   use Rishadblack\WireModals\Traits\WithLegacyEvents;
   use Rishadblack\WireModals\Traits\WithModals;

   /**
    * Kept for the existing modal classes. New modals should use WithModals directly.
    */
   trait WithDynamicComponent
   {
       use WithModals, WithLegacyEvents;
   }
   ```

   `modalOpen($name, $onlyModal)`, `modalClose($name)` and `closeComponent($name)` keep their positional
   signatures (`$onlyModal` is now named `$keepComponent`; fix any `onlyModal:` named-argument call found in
   phase 0). `alreadyExistsComponent`, `onConfirmAlert` and `onCancelAlert` come from `WithLegacyEvents`. The
   unused `isValidModalAction()` / `extractModalName()` helpers disappear; phase 0 showed no caller.
4. **Replace the host.** In both layouts replace `<livewire:core::dynamic-component-loader />` with
   `<livewire:wire-modals.host />`. Delete `Modules/Core/app/Pages/DynamicComponentLoader.php` and
   `Modules/Core/resources/views/pages/dynamic-component-loader.blade.php`. Update the comments in
   `Modules/Dev/app/Providers/DevModuleLivewireProvider.php` that mention `DynamicComponentLoader` (the missing
   component resolver itself stays; the host still resolves aliases through Livewire).
5. **Browser layer.** In `resources/js/app.js` delete the `modalOpen` listener, the `modalClose` listener, the
   `callEventFunc` listener, the jQuery `.callEvent` handler and the whole `Livewire.directive("load:component", …)`
   block. Keep the `print` listener and everything else. Add
   `import "../../vendor/rishadblack/wire-modals/resources/js/wire-modals.js";` after the Bootstrap/theme
   imports and before anything that depends on Livewire. Optionally import
   `../../vendor/rishadblack/wire-modals/resources/css/wire-modals.css`. Run `npm run build`.
6. **Modal component.** Rewrite `Modules/Theme/resources/views/components/backend/modal.blade.php` as a wrapper
   around `<x-wire-modals::modal>` that keeps the current contract: `id`, `size`, `position` (`centered` → `:centered="true"`,
   anything else → default), the `title`, `button` and `footer` slots, the theme close button in the footer, and
   `{{ $attributes }}` passthrough. Move its pushed `<style>` block (the `.modal-body` min/max height rules) into
   the theme SCSS once; use the package's `.wire-modals-body-tall` helper if it fits. The package component
   provides `wire:ignore.self`, the static backdrop, the Alpine wiring and stacking; do not duplicate them.
7. **Tabs.** `x-theme::backend.tab` keeps working because legacy mode still emits `set-tab`. Optionally switch it to
   `x-on:wire-modals:opened.window` with `$event.detail.modal`.
8. **Boost.** Add `rishadblack/wire-modals` to the `packages` list and `wire-modals-development` to the `skills`
   list in `boost.json`, run `php artisan boost:update`, and replace the body of
   `.ai/skills/modal-development/SKILL.md` with the ERP-specific conventions only (`lang()`, `ucan()`,
   `refreshDatatable`, file locations, the `UnitEdit` template rewritten to use `WithModals` directly) plus a
   pointer to the package skill for the mechanics. Update `.ai/guidelines/sylloo-erp.md` line about
   `WithDynamicComponent`.
9. **Clear caches:** `php artisan view:clear && php artisan config:clear`.

### Phase 1 acceptance (manual, in the browser; ask the user to click through, do not write tests)

- Product › Units: "Add" opens the modal, Save closes it and the table refreshes, Edit on two different rows shows
  the right record each time, Delete shows the SweetAlert, confirming deletes, dismissing does nothing.
- A modal with sub-modals: Order edit → serial/batch modal opens on top and closing it keeps the order modal.
- Media input: upload button opens the media modal and the chosen file flows back to the field.
- Website › Template market: the `postMessage`-driven `Livewire.dispatch('loadComponent', …)` still opens the
  template modal.
- Tabs inside a modal reset to the first tab on open.
- No `.modal-backdrop` left behind after any close; `storage/logs` shows no `wire-modals:` warnings while
  clicking through. A warning means an action or alias missing from the phase 0 lists: fix the config or add
  `#[ModalAction]`, never widen to `*`.

## Phase 2 — Remove what is left of the old system

- Grep for every leftover: `grep -rn "alreadyExistsComponent\|dynamic-component-loader\|DynamicComponentLoader\|callEventFunc\|callEvent\b\|load:component\b" Modules resources --include=*.php --include=*.blade.php --include=*.js`
  and remove dead references (the `wire:load:component` attributes stay until phase 3).
- Delete the compiled old bundle assets with the next `npm run build`.
- Remove `onConfirmValidationAlert` from `Modules/Core/app/Http/Common/LivewireComponent.php::validate()` only if
  the user agrees; it was referenced but never implemented, and it is unrelated to this package.

## Phase 3 — Modernise, module by module, then turn legacy off

Do this per module in separate commits so each can be reviewed:

1. Replace `wire:load:component="{action:'openUnitModal', component:'product::modal.unit-edit', id:'…'}"` with the
   attribute form `wire:modal="product::modal.unit-edit" wire:modal.action="openUnitModal" wire:modal.id="{{ … }}"`
   (or `<x-wire-modals::trigger component="…" action="…" :record-id="…">`). Extra values become `data-*`
   attributes and arrive under `$data['data']` with kebab → snake keys, all strings.
2. In `WithRowActions`, build the attributes with
   `Rishadblack\WireModals\Support\Trigger::make($component, $method)->id($row->getKey())->data([...])->attributes()`
   instead of string concatenation.
3. Mark the method that triggers open most often `#[ModalAction(default: true)]` and drop `wire:modal.action`
   from those triggers; mark delete/import methods `#[ModalAction]` and remove their names from
   `action_patterns` until only `open*Modal` is left.
4. Switch the two `Livewire.dispatch('loadComponent', …)` calls to `Livewire.find(hostId).load({...})` or to a
   server-side `$this->dispatch('wire-modals:load', payload: [...])->to('wire-modals.host')`.
5. Move the `ucan()` checks that sit at the top of every `open*Modal` into one `authorizeModalAction(string $action, array $payload): bool`
   override in `Modules\Core\Http\Common\LivewireComponent` (keep the in-method checks until the hook covers them).
6. When no module uses the old names, set `legacy.events => false` and `legacy.directive => false`, change
   `WithDynamicComponent` to `use WithModals;` only, then delete the alias trait and replace the `use` lines in
   the modal classes with `Rishadblack\WireModals\Traits\WithModals`.

## Rules while you work

- Payload shape is unchanged: actions receive `['id', 'data', 'action', 'component', 'modal', 'key', 'isConfirmed'?]`.
  `id` and `data` are request input; keep loading records with `find()` and keep `ucan()` checks.
- `component` is a reserved Livewire dispatch parameter: when you listen to the package's browser events use
  `owner` (`wire-modals:opened`), `target` (`wire-modals:dispose`) and `source` (`wire-modals:rejected`).
- Do not copy the deleted loader or directive back in any form; the host, the gate and the directive live in
  the package.
- Commit at the end of each phase with a message that lists the removed files.
- Stop and ask if an action name cannot be allowed with a pattern or an attribute, if a modal does not open after
  phase 1 (check the log first), or if a consumer calls a method that no longer exists.
