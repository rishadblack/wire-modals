# Package conventions to follow (taken from the sibling packages)

Compare against `packages/rishadblack/wire-tomselect` (closest in size and shape) and
`packages/rishadblack/i-reports` (Blade components, Livewire component registration, Pint + PHPStan + CI lint job).
When in doubt, open the sibling file and copy its shape.

## composer.json

Same field order and style as `wire-tomselect/composer.json`:

- `name` `rishadblack/wire-modals`; `description` from the pitch in `INSTRUCTIONS.md`; `license` MIT; `authors`
  S M Rishad, `rishad.black@gmail.com`, `https://smrishad.com`; `homepage` `https://github.com/rishadblack/wire-modals`;
  `keywords` `laravel, livewire, modal, bootstrap, dynamic-component, alpine`.
- `require`: `php ^8.3`, `illuminate/support ^11.0|^12.0|^13.0`, `illuminate/view ^11.0|^12.0|^13.0`,
  `livewire/livewire ^3.0|^4.0`. No `illuminate/database` (the package touches no DB).
- `require-dev`: `larastan/larastan ^3.0`, `laravel/pint ^1.20`, `orchestra/testbench ^9.0|^10.0|^11.0`,
  `pestphp/pest ^3.8|^4.0|^5.0`, `pestphp/pest-plugin-laravel ^3.0|^4.0|^5.0` (Pest 5 needs PHP 8.4, PHPUnit 13 and Laravel 13.23+, so resolve the lock with the PHP 8.4 composer to develop on it; older lanes fall back to Pest 4 or 3).
- `autoload` `Rishadblack\\WireModals\\ => src/`; `autoload-dev` `Rishadblack\\WireModals\\Tests\\ => tests`.
- `scripts`: `test`, `lint`, `lint:check`, `analyse`, `check` exactly as in `i-reports/composer.json`.
- `config.sort-packages`, `allow-plugins.pestphp/pest-plugin`; `extra.laravel.providers`;
  `minimum-stability stable`, `prefer-stable true`.

## Root files

| File | Copy from | Notes |
|---|---|---|
| `LICENSE`, `license.md` | wire-tomselect | MIT, same year/author line |
| `contributing.md` | wire-tomselect | change repo URL |
| `.gitignore` | wire-tomselect | `vendor`, `composer.lock`, `.phpunit.result.cache`; add `build/` |
| `pint.json` | i-reports | laravel preset, exclude `vendor` |
| `phpstan.neon` | i-reports | level 6, paths `src`, `config`; keep the `view-string` ignore; drop the migration/env ignores |
| `phpunit.xml` | wire-tomselect | suite `Package`, source `src/` |
| `.github/workflows/tests.yml` | i-reports | lint job (Pint + PHPStan on 8.4) + pest matrix PHP 8.3/8.4 × Laravel 11/12/13 × Livewire 3/4, with the Laravel 11 advisory opt-out step; extensions `mbstring, pdo_sqlite` |
| `changelog.md` | wire-tomselect | Keep-a-Changelog headings: `## 1.0.0 - YYYY-MM-DD` with `### Added / Changed / Fixed / Security` |
| `readme.md` | wire-tomselect | see section order below |

Each package is its own git repository (there is a `.git` folder inside each sibling). Run `git init` in
`packages/rishadblack/wire-modals` and make a first commit once Phase 1 passes. Do not add the folder to the
sandbox repo's commits; the root `.gitignore` or the path-repository symlink handles it the same way as the
siblings (check `git status` at the root to confirm the folder is not tracked; if it is, ask).

## Service provider pattern

`WireTomselectServiceProvider` is the template: `register()` merges config; `boot()` loads views, publishes
config and views under `runningInConsole()`. Add from `IReportsServiceProvider`:
`Blade::componentNamespace(...)` and `Livewire::component(...)`. Tag names: `wire-modals-config`,
`wire-modals-views` (wire-tomselect style with a hyphen).

## Tests

- `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`, registers `LivewireServiceProvider` and the
  package provider, sets `app.key`, and registers fixture components with `Livewire::component()` in `setUp()`
  (see `wire-tomselect/tests/TestCase.php`). Add a fixture view namespace the same way.
- `tests/Pest.php`: `pest()->extend(TestCase::class)->in('Feature');`
- Fixtures live in `tests/Fixtures` (classes) and `tests/Fixtures/views` (Blade).
- Use `Livewire::test()` for the host and the fixtures; assert dispatched events with `assertDispatched`,
  `assertDispatchedTo`, `assertNotDispatched`; assert rejections with `Log::spy()` / `Log::shouldReceive`.
- Read the `testing-best-practices` skill before writing tests. Name tests by behaviour, one behaviour per test.
- Run `vendor/bin/pest --compact` from the package folder. Browser tests do not live in the package (Testbench has
  no browser); they live in the sandbox app.

## Boost guideline and skill

Laravel Boost collects `resources/boost/guidelines/core.blade.php` from every installed package into the host
app's `AGENTS.md` (that is where the `=== rishadblack/wire-tomselect/core rules ===` block comes from) and exposes
`resources/boost/skills/<skill-name>/SKILL.md` as an activatable skill.

- `core.blade.php`: a `## Wire Modals` heading and 8–10 bullets, same voice as the wire-tomselect guideline:
  what the package is, "activate the `wire-modals-development` skill whenever...", the three building blocks
  (host once per layout, trait on the component, `wire:modal` trigger), the action rules (`open*Modal` or
  `#[ModalAction]`, `authorizeModalAction()`), payload keys, `keepComponent`, Bootstrap requirement, the JS import,
  and the security line ("`id` and `data` are request input; authorise in the action").
- `SKILL.md`: frontmatter `name: wire-modals-development` and a `description:` listing concrete triggers (class
  uses `Rishadblack\WireModals\Traits\WithModals`, `<x-wire-modals::modal>`, `<x-wire-modals::trigger>`,
  `wire:modal`, `modalOpen`/`modalClose`/`closeComponent`, `#[ModalAction]`, the host component, legacy flags,
  testing with Pest). Body sections, mirroring `wire-tomselect-development/SKILL.md`: when to use, requirements and
  setup (JS import, host in layout, Bootstrap), creating a modal component (full example class + Blade view, in the
  same style as the ERP skill's `UnitEdit` but ERP-free), triggers (Blade component, `Trigger` builder, table
  buttons), re-entry and multiple modals per component, sub-modals with `keepComponent`, delete/confirm pattern
  with `#[ModalAction]`, authorisation, configuration reference, legacy mode for existing apps, testing recipes,
  troubleshooting (modal id mismatch, Bootstrap missing, component not allowed, action rejected).

## Readme section order (match wire-tomselect)

Title + pitch + 3-line example → Contents → Requirements (table) → Installation (composer, JS import, host in
layout, Bootstrap, publish config) → Your first modal (component + view + trigger) → Configuration reference →
Triggers (Blade component, `Trigger` builder, `wire:modal` JSON shape) → Actions and payload → Re-entry and
several modals per component → Sub-modals and keep-alive → Delete with confirmation → Authorisation →
Security notes → Legacy mode (for apps migrating from a hand-rolled loader) → Testing → Customising the markup →
Troubleshooting → License.

## Sandbox app integration (this repository)

The root app is a Laravel 13 sandbox with Bootstrap 5.3 and Vite (`resources/js/app.js` imports `bootstrap` and
the wire-tomselect script; `resources/css/app.css` imports Bootstrap CSS). Follow how the other packages are
tried out:

- `composer.json`: add a `repositories` entry `{ "name": "rishadblack/wire-modals", "type": "path", "url": "./packages/rishadblack/wire-modals", "options": { "symlink": true } }`
  and `"rishadblack/wire-modals": "*@dev"` in `require`.
- `resources/js/app.js`: `import '../../vendor/rishadblack/wire-modals/resources/js/wire-modals.js';` after the
  Bootstrap import. `resources/css/app.css`: import the package CSS if you ship one. Run `npm run build`.
- Layout: the existing pages are full-page Livewire components (`Route::livewire(...)` in `routes/web.php`) using
  the app layout; add `<livewire:wire-modals.host />` once in that layout.
- Page: `app/Livewire/Pages/ModalsPlayground.php` + `resources/views/livewire/pages/modals-playground.blade.php`,
  pattern of `HelpersPlayground` / `LocationSearch`. Modal component: `app/Livewire/Modals/NoteEdit.php` +
  `resources/views/livewire/modals/note-edit.blade.php`. Use a small `notes` table via a migration + model +
  factory (`php artisan make:model Note -mf --no-interaction`), since the sandbox already has users and regions.
- Route `Route::livewire('/modals', ModalsPlayground::class)->name('modals');` and a button on
  `resources/views/welcome.blade.php` labelled `Modals Playground (wire-modals)`.
- Feature test `tests/Feature/ModalsPlaygroundTest.php` (page renders, host present) and browser test
  `tests/Browser/WireModalsTest.php` following `tests/Browser/UsersReportViewerTest.php` (`visit(route(...))`,
  `assertScript`, `assertNoJavaScriptErrors`). Run browser tests in the background with a timeout.

## Style rules that apply to every PHP file

From the root `AGENTS.md`: curly braces always, constructor promotion, explicit return types and parameter
types, PHPDoc array shapes, descriptive names, `php artisan make:*` for app files, `vendor/bin/pint --dirty --format agent`
before finishing. Config files use `env()` only where the sibling configs do (they do not); keep config static.
