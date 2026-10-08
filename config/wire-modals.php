<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Host component
    |--------------------------------------------------------------------------
    |
    | The Livewire name the host (loader) component is registered under. Place
    | it once per layout with <livewire:wire-modals.host />.
    |
    */

    'host_component' => 'wire-modals.host',

    /*
    |--------------------------------------------------------------------------
    | Callable actions
    |--------------------------------------------------------------------------
    |
    | A trigger may only call a public method that either carries the
    | #[ModalAction] attribute or whose name matches one of these patterns
    | (* is a wildcard). Methods declared by Livewire itself or by this
    | package are never callable, whatever the patterns say.
    |
    */

    'action_patterns' => ['open*Modal'],

    /*
    |--------------------------------------------------------------------------
    | Mountable components
    |--------------------------------------------------------------------------
    |
    | Only registered Livewire components that use the WithModals trait and
    | whose name matches one of these patterns can be mounted by the host.
    | Fully qualified class names are refused unless allow_class_names is on.
    |
    */

    'allowed_components' => ['*'],

    'allow_class_names' => false,

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | max_mounted caps how many components one host keeps alive at a time.
    | max_payload_bytes caps the JSON size of the trigger "data" array.
    |
    */

    'max_mounted' => 15,

    'max_payload_bytes' => 8192,

    /*
    |--------------------------------------------------------------------------
    | Trigger data attributes
    |--------------------------------------------------------------------------
    |
    | Every data-* attribute on a trigger element becomes a payload value,
    | except attributes whose name starts with one of these prefixes
    | (written without the leading "data-").
    |
    */

    'data_exclusions' => ['bs-', 'wire-modals-'],

    /*
    |--------------------------------------------------------------------------
    | Modal defaults
    |--------------------------------------------------------------------------
    |
    | Defaults for <x-wire-modals::modal>. backdrop accepts true, false or
    | 'static'. size accepts null, 'sm', 'lg' or 'xl'.
    |
    */

    'modal' => [
        'backdrop' => 'static',
        'keyboard' => false,
        'size' => null,
        'centered' => false,
        'scrollable' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rejections
    |--------------------------------------------------------------------------
    |
    | When a payload, component or action is refused the request is ignored
    | and a "wire-modals:rejected" browser event is dispatched. With
    | log_rejections on, a warning naming the component, the action and the
    | reason is written to the log. Payload data is never logged.
    |
    */

    'log_rejections' => true,

    /*
    |--------------------------------------------------------------------------
    | Legacy mode
    |--------------------------------------------------------------------------
    |
    | For applications migrating from a hand-rolled loader. "events" makes the
    | host also answer the loadComponent / closeComponent Livewire events and
    | makes modals also react to the modalOpen / modalClose browser events
    | (and emit set-tab). "directive" also registers wire:load:component.
    |
    */

    'legacy' => [
        'events' => false,
        'directive' => false,
    ],

];
