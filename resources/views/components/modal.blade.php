<div {{ $attributes->class(['modal', 'fade'])->merge([
        'id' => $id,
        'tabindex' => '-1',
        'aria-hidden' => 'true',
        'aria-labelledby' => $id.'-title',
        'data-bs-backdrop' => $backdropAttribute(),
        'data-bs-keyboard' => $keyboard ? 'true' : 'false',
        'data-wire-modals-legacy' => $legacyEvents() ? 'true' : 'false',
    ]) }}
    wire:ignore.self
    x-data="wireModalsModal"
    x-on:wire-modals:open.window="open"
    x-on:wire-modals:close.window="close"
    @if ($legacyEvents())
    x-on:modal-open.camel.window="open"
    x-on:modal-close.camel.window="close"
    @endif>
    <div class="{{ $dialogClasses() }}">
        <div class="modal-content">
            @if ($title || isset($button) || $closeButton)
                <div class="modal-header">
                    @if ($title)
                        <h5 class="modal-title" id="{{ $id }}-title">{{ $title }}</h5>
                    @endif
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        @isset($button)
                            <span {{ $button->attributes }}>{{ $button }}</span>
                        @endisset
                        @if ($closeButton)
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        @endif
                    </div>
                </div>
            @endif
            <div class="modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div {{ $footer->attributes->class(['modal-footer']) }}>
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
