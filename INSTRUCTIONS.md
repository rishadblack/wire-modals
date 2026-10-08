# wire-modals — build instructions for the implementing agent

> **Build status (2026-10-08):** Phases 0–7 are done. The package source, config, views, JS, Boost guideline
> and skill, readme, changelog and the Pest suite (118 tests) exist and pass; Pint and PHPStan (level 6) are
> clean; the sandbox app has the `/modals` playground with feature and browser tests. Deviations from the
> first draft, all reflected in `docs/`: Livewire's `dispatch()` reserves the `component` parameter, so events
> use `owner` / `target` / `source`; the host pre-checks the action before mounting; `modalKeepAlive()` was
> dropped; the Blade trigger takes `record-id` (not `id`) so HTML ids pass through; Boost compiles
> `core.blade.php` as Blade, so braces in it must be escaped with `@`. Not done on purpose: no git commit in
> this folder yet (repo initialised, nothing committed) and no change in sylloo-erp. Use the rest of this file
> as the spec when extending the package.

You are building a new Composer package, `rishadblack/wire-modals`, inside this folder. It extracts the
"dynamic component / modal" system from the Sylloo ERP (`C:\Office\sylloo-project\sylloo-erp`) into a
reusable Laravel + Livewire package that follows the structure of the sibling packages in
`packages/rishadblack/` (`wire-tomselect`, `i-reports`, `larahelpers`).

This file is the entry point. Read it fully, then read the four documents in `docs/` in this order:

1. `docs/how-sylloo-does-it.md` — how the current system works, file by file, request by request. You must
   understand it before designing anything, because 107 ERP classes depend on its exact behaviour.
2. `docs/architecture.md` — the package design: file tree, public API, events, config, JS, Blade components,
   test matrix, browser scenarios and the later ERP migration checklist.
3. `docs/security-and-performance.md` — the problems found in the ERP code and the mitigations the package
   MUST ship with. Every item there is a requirement, not a suggestion.
4. `docs/package-conventions.md` — the exact scaffolding, testing, CI, docs and Boost-skill conventions the
   sibling packages use, plus how to wire the package into this sandbox app.

## One-line pitch (use this wording in the readme and the Boost guideline)

> On-demand Livewire components with Bootstrap 5 modals: mount any Livewire component the first time a button
> is clicked, call an action on it, keep it alive while its modal is open, and unmount it when the modal
> closes. The component owns its own modal markup, so one component can drive several modals.

That pitch is the differentiator from `wire-elements/modal` (4.2M installs). Do not describe the package as
"a modal component"; it is a lazy component host whose main presenter is a Bootstrap modal.

## Non-negotiable decisions (already made — do not re-open them)

| Topic | Decision |
|---|---|
| Package name | `rishadblack/wire-modals` (folder `packages/rishadblack/wire-modals`) |
| Namespace | `Rishadblack\WireModals` |
| Service provider | `Rishadblack\WireModals\WireModalsServiceProvider` |
| Config file | `config/wire-modals.php`, key `wire-modals` |
| View namespace and Blade component namespace | `wire-modals` → `<x-wire-modals::modal>`, `<x-wire-modals::trigger>` |
| Livewire host component name | `wire-modals.host` → `<livewire:wire-modals.host />` once per layout |
| Consumer trait | `Rishadblack\WireModals\Traits\WithModals` |
| Legacy trait (Sylloo only) | `Rishadblack\WireModals\Traits\WithLegacyEvents` |
| Trait public methods (keep ERP names) | `modalOpen(string $modal, bool $keepComponent = false)`, `modalClose(string $modal)`, `closeComponent(?string $component = null)` |
| New directive | `wire:modal="<json>"`; legacy alias `wire:load:component` only when `legacy.directive` is on |
| Event names | Namespaced `wire-modals:*` (see architecture doc). ERP names only through the legacy layer |
| UI driver | Bootstrap 5.3 modal, same as `i-reports`. No Tailwind, no jQuery, no SweetAlert dependency |
| PHP / Laravel / Livewire | `php ^8.3`, `illuminate/* ^11.0\|^12.0\|^13.0`, `livewire/livewire ^3.0\|^4.0`, same as wire-tomselect |
| Tests | Pest + Orchestra Testbench inside the package; Pest browser tests in the sandbox app `tests/Browser` |
| Code style and analysis | Pint (laravel preset) + Larastan level 6, like `i-reports` |

## Hard rules while you work

- Follow the project `AGENTS.md` (Laravel Boost guidelines) in the repo root. In particular: match sibling
  package files for structure and naming, run `vendor/bin/pint --dirty --format agent` on PHP you changed,
  write tests for behaviour, and do not add dependencies to the package beyond those listed above.
- Do NOT modify anything under `C:\Office\sylloo-project\sylloo-erp`. The ERP migration is a separate task
  described at the end of `docs/architecture.md`; it happens after the package is released.
- Do not copy ERP code verbatim. Rewrite it with the security fixes. The ERP code is the behavioural
  reference, not the implementation.
- Every public method and every config key you add must be exercised by a test.
- Keep the Blade modal component free of ERP theme classes (`x-theme::*`, `waves-effect`, `tf-icon`, `lang()`,
  `ucan()`). The package knows nothing about the ERP.
- The package must work under a strict Content Security Policy: no `eval`, no `new Function`, no inline
  scripts in package views.
- On this machine `composer` runs on PHP 8.3 while `php` is 8.4 (see memory note `composer-php-version`).
  The package requires `^8.3`, so plain `composer` is fine inside the package folder. Pint on Windows reports
  CRLF noise; CI on Linux is the real Pint check.
- Start long test runs (especially browser tests) in the background with a timeout and check results after
  about 30 seconds; do not block on them.

## Work plan (do the phases in order; each has an acceptance check)

### Phase 0 — Verify assumptions (no files yet)

Run these and record the answers in your working notes:

- `composer show livewire/livewire` in the sandbox app, then read the Livewire source to confirm:
  - trait lifecycle hooks exist: `mount{TraitName}()` is called with the mount params
    (`SupportLifecycleHooks::callTraitHook('mount', $params)`);
  - a component can declare dynamic listeners with `protected function getListeners(): array` in Livewire 3
    and 4 (the host uses it for legacy event names);
  - how to resolve a component alias to a class without instantiating it (Livewire 3:
    `app(\Livewire\Mechanisms\ComponentRegistry::class)->getClass($name)`; find the Livewire 4 equivalent);
  - that `Livewire.dispatchTo(name, event, payload)` and `Livewire.dispatch(event, payload)` exist in the
    browser API of both versions, and whether `Livewire.interceptRequest` exists in both. If it does not,
    implement the trigger "loading" state with the `commit` hook, or with the `wire-modals:opened` and
    `wire-modals:already-mounted` browser events plus a timeout fallback.
- Confirm Bootstrap 5 is available as `window.bootstrap` in the sandbox app (`resources/js/app.js` imports
  `bootstrap`). The package JS must not import Bootstrap itself; it uses the global through a configurable
  accessor.

Acceptance: you can state, with file references, how each of the above works in Livewire 3 and 4.

### Phase 1 — Scaffold

Create the package skeleton exactly as described in `docs/package-conventions.md`: `composer.json`,
`LICENSE`, `license.md`, `contributing.md`, `readme.md` (skeleton), `changelog.md`, `.gitignore`,
`.github/workflows/tests.yml`, `pint.json`, `phpstan.neon`, `phpunit.xml`, `config/wire-modals.php`,
`src/WireModalsServiceProvider.php`, `tests/TestCase.php`, `tests/Pest.php`, `resources/views/`,
`resources/js/wire-modals.js`, `resources/boost/guidelines/core.blade.php`,
`resources/boost/skills/wire-modals-development/SKILL.md` (skeleton). Initialise a git repository in the
folder (each sibling package is its own repo). Run `composer install` inside the package and make
`vendor/bin/pest` run with one placeholder test for the provider.

Then register the package in the sandbox app: add the path repository entry and
`"rishadblack/wire-modals": "*@dev"` to the root `composer.json` (this is the one dependency change this
task authorises), run `composer update rishadblack/wire-modals`, and check `php artisan about` lists the
provider.

Acceptance: `composer test` passes in the package; `php artisan config:show wire-modals` works in the app.

### Phase 2 — Core PHP

Implement, per `docs/architecture.md`:

- `Http\Livewire\Host` (the loader) with `#[Locked] public array $components`.
- `Traits\WithModals` with `modalOpen`, `modalClose`, `closeComponent`, the `mountWithModals` hook, the
  `wire-modals:already-mounted` listener and the `runModalAction()` dispatcher.
- `Support\Payload` (normalise and validate the client payload), `Support\ActionResolver` (decide whether a
  method may be called), `Attributes\ModalAction`, `Support\Trigger` (safe attribute builder),
  `Exceptions\*`.
- `View\Components\Modal` and `View\Components\Trigger` with their views.

Acceptance: the Pest feature tests listed in `docs/architecture.md` under "Test matrix" pass, including every
negative test in `docs/security-and-performance.md`.

### Phase 3 — Browser side

Write `resources/js/wire-modals.js`: the `wire:modal` directive, the window listeners for
`wire-modals:open`, `wire-modals:close` and `wire-modals:dispose`, the Bootstrap adapter, the
hide-then-unmount flow, and the loading state. No library other than the Livewire and Bootstrap globals.
Optional `resources/css/wire-modals.css` for the `.modal-body` height helpers.

Acceptance: the sandbox page (Phase 5) works end to end with no console errors.

### Phase 4 — Legacy layer

`Traits\WithLegacyEvents`, host `getListeners()` legacy names, JS legacy flags (`wire:load:component`,
`modalOpen`, `modalClose`, `closeComponent`, `set-tab`). All off by default. Tests prove both that the
legacy names work when enabled and that they are inert when disabled.

### Phase 5 — Sandbox app page and browser tests

In this app (not in the package): `App\Livewire\Modals\NoteEdit` (a modal component using the trait, with a
nested sub-modal using `keepComponent`), `App\Livewire\Pages\ModalsPlayground` with a list and trigger
buttons, route `/modals`, a button on `welcome.blade.php`, `<livewire:wire-modals.host />` in the layout,
and the JS import in `resources/js/app.js`. Then `tests/Browser/WireModalsTest.php` covering the scenarios in
`docs/architecture.md` under "Browser scenarios". Run `npm run build` after JS changes.

### Phase 6 — Documentation

Finish `readme.md` (same section order as `wire-tomselect/readme.md`), the Boost guideline, the Boost skill
(model it on `wire-tomselect-development/SKILL.md` and on the ERP skill
`.ai/skills/modal-development/SKILL.md`, rewritten for the package API), and the `changelog.md` entry
`1.0.0`.

### Phase 7 — Quality gate

`composer check` in the package (pint, phpstan, pest) and the sandbox browser tests must pass. Then report:
what was built, the test counts, anything left open, and the ERP migration checklist from
`docs/architecture.md`.

## When you must stop and ask

- If a Livewire 3 vs 4 difference makes one of the non-negotiable decisions impossible.
- If the only way to keep the zero-change ERP migration is to weaken a security requirement. Security wins;
  propose the smallest ERP change instead.
- Anything that would add a Composer or npm dependency.
