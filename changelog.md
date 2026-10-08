# Changelog

All notable changes to `rishadblack/wire-modals` are documented in this file.

## 1.0.0 - 2026-10-08

### Added

- Host component (`<livewire:wire-modals.host />`) that mounts Livewire components on demand, re-enters mounted ones and unmounts them when their modal closes.
- `WithModals` trait: `modalOpen()`, `modalClose()`, `closeComponent()`, `authorizeModalAction()` hook, `modalTrigger()` builder.
- `#[ModalAction]` attribute and `action_patterns` config to decide which methods a trigger may call.
- `wire:modal` directive driven by plain attributes (`wire:modal`, `wire:modal.action`, `wire:modal.id`, `wire:modal.key`, `data-*`).
- `<x-wire-modals::modal>` Bootstrap 5 shell and `<x-wire-modals::trigger>` element.
- `Trigger` builder for server-side rendered attributes (tables, action columns).
- Legacy layer (`WithLegacyEvents`, `legacy.events`, `legacy.directive`) for applications migrating from a hand-rolled loader.
- Stacked modals: each newly shown modal and its backdrop are raised above the open ones, and the body stays locked while any modal is visible, so modal chains and cross-component modals work without extra code.

### Security

- The host's component list is locked; only registered components that use the trait and match `allowed_components` can be mounted.
- Only public application methods marked `#[ModalAction]` or matching `action_patterns` are callable; Livewire and package methods never are.
- Trigger values are read as attributes and validated on both sides; nothing from markup is evaluated.
- Action names are canonicalised through reflection before `authorizeModalAction()` and the call, so a differently cased request cannot slip past a name-based authorisation hook.
- Component names cannot contain `..` or start or end with a separator; rejected payload strings are sanitised before logging.
