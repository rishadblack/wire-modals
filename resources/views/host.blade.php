<div x-data="wireModalsHost"
    data-wire-modals-host
    data-wire-modals-legacy-events="{{ $legacyEvents ? 'true' : 'false' }}"
    data-wire-modals-legacy-directive="{{ $legacyDirective ? 'true' : 'false' }}"
    data-wire-modals-data-exclusions="{{ $dataExclusions }}"
    x-on:wire-modals:hidden.window="hidden"
    x-on:wire-modals:dispose.window="dispose">
    @foreach ($entries as $entry)
        <div wire:key="wire-modals-{{ $entry['slug'] }}" data-wire-modals-entry="{{ $entry['component'] }}">
            <livewire:dynamic-component :is="$entry['component']" :key="'wire-modals-'.$entry['slug']" :wireModalsPayload="$entry['payload']" />
        </div>
    @endforeach
</div>
