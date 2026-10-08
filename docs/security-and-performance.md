# Security and performance review of the ERP implementation, and what the package must do about it

Everything below was verified against the files named in `how-sylloo-does-it.md`. Each finding lists the
mitigation the package ships. Write a failing test for each "Test" line before implementing the fix.

## Security

### S1. The host's component list is writable from the browser (High)

`DynamicComponentLoader::$components` is a plain `public $components = []`. Livewire lets the browser update any
public property that is not `#[Locked]`, so a user can call `$wire.set('components', [{component: 'any.registered.alias', action: 'anyMethod', data: {...}}])`
and the host will mount that component and run that method on the next render. Combined with S2 and S3 this is
"mount anything, call anything".

- Mitigation: `#[Locked] public array $components`. All writes happen server-side in `load()` / `unmount()`.
- Test: `Livewire::test(Host::class)->set('components', [...])` throws Livewire's locked-property exception.

### S2. Any registered Livewire component can be mounted with attacker-chosen mount params (High)

The loader mounts whatever alias the payload names via `<livewire:dynamic-component :is="...">`. Nothing checks
that the target is meant to be a modal.

- Mitigation: `ComponentResolver::assertAllowed()` requires (a) a registered alias, (b) the class uses
  `WithModals`, (c) the alias matches `allowed_components`. Class names (`App\Livewire\...`) are rejected unless
  `allow_class_names` is true. Rejections are logged with alias and reason only, never the payload data.
- Test: a fixture without the trait is never mounted; an alias outside the allow-list is never mounted; a
  class-name alias is rejected by default.

### S3. Arbitrary public method execution (High)

`alreadyExistsComponent()` does `method_exists($this, $data['action'])` then `$this->{$data['action']}($data)`.
`method_exists` is true for every method on `Livewire\Component` and its traits, so `render`, `mount`,
`__dispatch`, `dispatch`, `skipRender`, `redirect`, `js`, `stream`, `validate`, plus every protected helper.
The guards `isValidModalAction()` / `extractModalName()` exist but are never called.

- Mitigation: `ActionResolver` (see architecture): public, non-static, non-magic, declared outside `Livewire\*`
  and outside the package traits, and either `#[ModalAction]` or an `action_patterns` match. `Payload` rejects
  names starting with `__`.
- Test: the deny list in the architecture test matrix, plus `#[ModalAction]` on a method with a non-matching name
  is allowed.

### S4. Arbitrary property write from the payload (High)

`$data['data']['extra'][2]` → `list($key, $value) = explode(':', ...)` → `$this->{$key} = $value`. Bypasses
`#[Locked]` (direct server-side assignment), can overwrite record ids, and no consumer uses it.

- Mitigation: do not implement. `Payload` drops unknown keys and never writes properties.
- Test: a payload with `data.extra` reaches the action unchanged and sets no property.

### S5. Client-supplied JavaScript is evaluated (High under CSP, Medium otherwise)

The directive runs `new Function("return " + expression)()` on the attribute value. Attribute values are built by
string interpolation in Blade (`id:'{{ $item->id }}'`) and in `WithRowActions::rowActionWithId()` (raw object
literal via `$data`). A record field containing `'}` breaks out of the literal; under a CSP it is blocked outright.

- Mitigation: `wire:modal` carries JSON and the browser uses `JSON.parse`. `Trigger` and the Blade trigger
  component encode with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. The legacy directive only
  normalises quotes/keys to JSON and never evaluates; failures are logged and ignored.
- Test: `TriggerTest` escaping cases; a browser scenario with an id containing `"'<>&`.

### S6. Payload is trusted as data (Medium)

`id` and `data` flow straight into actions. The ERP actions do `Model::find($data['id'])` and dispatch
`$data['data']['listener']` as an event name (MediaFileModal). The package cannot fix consumer logic, but it can
bound the input.

- Mitigation: `Payload` limits types, sizes, depth and leaf count; document in the readme and skill that `id`
  and `data` are untrusted request input, that actions must authorise (`authorizeModalAction()` hook), and that
  values used as event names or class names must be allow-listed by the consumer.
- Test: `PayloadTest` limits.

### S7. Confirm round-trip re-enters with client data (Medium)

SweetAlert `inputAttributes` go to the browser and come back as the payload for `onConfirmAlert`. The ERP calls
the action again with whatever came back, including `isConfirmed: true`, which the browser can set directly to
skip the dialog.

- Mitigation inside the package: `WithLegacyEvents` passes the returned payload through `runModalAction()`, so
  S3's method rules apply. Document that `isConfirmed` is a UX flag, not an authorisation; destructive actions
  must still authorise server-side (the ERP already checks `ucan()` in `open*` but not always in `*Delete`).
  Optional improvement to describe in the readme for the ERP: sign the confirm payload (HMAC of `component|action|id`)
  when it is created and verify it on return.
- Test: legacy confirm test ensures the action is still gated by `ActionResolver`.

### S8. Information leaks (Low)

`console.log(event.detail)` on every modal close; rejection paths return `false` silently.

- Mitigation: no console output except `console.error` on malformed trigger JSON and one-time Bootstrap-missing
  error. Server-side rejections `Log::warning` alias/action/reason only.

### S9. Denial by accumulation (Low)

Nothing caps mounted entries; each mounted component adds a Livewire snapshot to every page request and DOM to
the page. Sub-modals and `onlyModal` paths never unmount.

- Mitigation: `max_mounted` (default 15) with a logged, browser-visible rejection; `modalKeepAlive()` makes the
  permanent case explicit.

### S10. Attribute scraping sends unrelated `data-*` to the server (Low, new form)

Reading every `data-*` on the trigger would include Bootstrap tooltip state and the package's own loading marker,
and lets any key be added through DevTools.

- Mitigation: exclusion prefixes (`data-bs-`, `data-wire-modals-`, configurable); server-side `Payload` bounds and
  never trusts keys.
- Test: a trigger with `data-bs-toggle` sends no `bs_toggle`; `data-wire-modals-loading` is never sent.

### S11. Prototype-pollution-shaped keys (Low, new form)

`data-__proto__` is a valid attribute name.

- Mitigation: payload built on a null-prototype object; keys must match `^[a-z][a-z0-9_]*$` in the browser and
  again on the server.
- Test: browser scenario with a planted `data-__proto__` attribute and a server `PayloadTest` case.

### S12. Triple binding of the directive (Correctness + performance, new form)

Livewire runs a directive once per matching attribute, so `wire:modal`, `wire:modal.action` and `wire:modal.id`
would bind three click handlers and send three loads.

- Mitigation: bind only when `directive.modifiers` is empty.
- Test: browser scenario asserts exactly one host request per click.

### S13. Double click (Low, new form)

Reading at click time means two fast clicks send two loads. The host dedupes, but the second request is waste.

- Mitigation: per-element pending flag cleared when the `$wire.load()` promise settles.

### S14. Action name casing bypasses consumer authorisation (High, found in review of the first build)

PHP method names are case-insensitive. `method_exists()` and `ReflectionMethod` accept `NoteDelete` for a method
declared as `noteDelete`, so the gate passed, but the string the client sent was handed to
`authorizeModalAction()` and used for the dynamic call. A consumer hook written as
`match ($action) { 'noteDelete' => can('delete'), default => can('update') }` would authorise a delete with
update rights.

- Mitigation: `ActionResolver::resolve()` returns the canonical name from reflection; the trait and the host use
  only that name for pattern matching, the hook and the call.
- Test: `WithModalsTest` sends `NoteDelete` while the hook denies `noteDelete` and expects a rejection; when
  allowed, the action receives `noteDelete` in its payload.

### S15. Component names that walk the filesystem (Low)

Livewire 4 maps dots in a component name to directories when it looks for single-file components. The name
pattern allowed `..`, leading and trailing separators.

- Mitigation: `Payload::assertComponentName()` rejects `..` and names starting or ending with `.`, `:` or `-`.
  The trait and allow-list checks still apply afterwards.

### S16. Log and event injection from rejected payloads (Low)

The host logged the raw `component` and `action` strings of a payload that had just failed validation.

- Mitigation: control characters are stripped and both strings are capped at 120 characters before logging and
  before the `wire-modals:rejected` browser event. Rejection logging can be switched off (`log_rejections`)
  and remains a per-request cost an attacker can trigger, like any Livewire request.

## Performance

### P1. Host re-renders on the re-entry path

`loadComponent` returns without `skipRender()` when the component is already mounted, so the host, its wrapper
divs and its diff run for nothing on every re-open. Fix: `skipRender()` on that path. Children are keyed, so
Livewire does not re-render them when the host renders, but the host render itself is avoidable.

### P2. Global event fan-out

`closeComponent`, `modalOpen`, `modalClose` and `loadComponent` are broadcast to every Livewire component on the
page; each one with a matching listener makes a request. `->to()` scoping exists only for `alreadyExistsComponent`.
Fix: every Livewire event in the package is dispatched `->to()` a specific component (host or alias); browser-only
events (`wire-modals:open/close/dispose/opened`) are plain `dispatch()` because no component listens to them.
Measure: in the sandbox, one click must produce exactly one Livewire request to the host plus the child mount,
and one request on close.

### P3. Listener leak on `hidden.bs.modal`

Each `modalOpen` adds a new listener and never removes it; after N re-opens one close dispatches N `closeComponent`
events (N requests). Fix: `{ once: true }` plus a `WeakMap` guard per element.

### P4. Per-modal `<style>` push

`x-theme::backend.modal` pushes the same `<style>` block for every modal instance on the page (7 modals in a
view = 7 copies). Fix: the package component emits no CSS; ship `resources/css/wire-modals.css` once.

### P5. Trigger loading state via a global request interceptor

`Livewire.interceptRequest` registered per click intercepts all requests until the next success. Fix: clear the
loading state from the `wire-modals:opened` / `already-mounted` / `rejected` events with a timeout fallback; no
global interception.

### P6. Snapshot size

Each entry stores `id`, `data`, `action`, `component`, `modal`. Keep that (it is small) but enforce
`max_payload_bytes` so a large `data` cannot bloat every subsequent request of the host.

### P7. Collection churn

`collect($this->components)->contains(...)->push(...)->toArray()` on each call is negligible; keep the entries
keyed by `key` in a plain array for O(1) lookup instead of `contains()`.

## Verification checklist for the implementing agent

- [ ] Every High item has a test that failed before the fix.
- [ ] `grep -rn "new Function\|eval(" resources/js` returns nothing.
- [ ] The sandbox page passes with `Content-Security-Policy: script-src 'self'` set on the response (add a
      temporary middleware while testing; do not commit it).
- [ ] One click → one host request; one close → one host request (check the Network tab or Livewire's
      `request` hook in a browser test).
- [ ] No `.modal-backdrop` remains after `closeComponent()` is called while a modal is open.
