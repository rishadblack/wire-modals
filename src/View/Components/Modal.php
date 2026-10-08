<?php

namespace Rishadblack\WireModals\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A Bootstrap 5 modal shell. Its id must equal the name passed to modalOpen().
 */
class Modal extends Component
{
    public string|bool $backdrop;

    public bool $keyboard;

    public ?string $size;

    public bool $centered;

    public bool $scrollable;

    public function __construct(
        public string $id,
        public ?string $title = null,
        string|bool|null $backdrop = null,
        ?bool $keyboard = null,
        ?string $size = null,
        ?bool $centered = null,
        ?bool $scrollable = null,
        public string|bool $fullscreen = false,
        public bool $closeButton = true,
    ) {
        $this->backdrop = $backdrop ?? config('wire-modals.modal.backdrop', 'static');
        $this->keyboard = $keyboard ?? (bool) config('wire-modals.modal.keyboard', false);
        $this->size = $size ?? config('wire-modals.modal.size');
        $this->centered = $centered ?? (bool) config('wire-modals.modal.centered', false);
        $this->scrollable = $scrollable ?? (bool) config('wire-modals.modal.scrollable', false);
    }

    public function backdropAttribute(): string
    {
        if ($this->backdrop === 'static') {
            return 'static';
        }

        return $this->backdrop ? 'true' : 'false';
    }

    public function dialogClasses(): string
    {
        $classes = ['modal-dialog'];

        if ($this->size !== null && $this->size !== '') {
            $classes[] = 'modal-'.$this->size;
        }

        if ($this->centered) {
            $classes[] = 'modal-dialog-centered';
        }

        if ($this->scrollable) {
            $classes[] = 'modal-dialog-scrollable';
        }

        if ($this->fullscreen === true) {
            $classes[] = 'modal-fullscreen';
        } elseif (is_string($this->fullscreen) && $this->fullscreen !== '') {
            $classes[] = 'modal-fullscreen-'.$this->fullscreen.'-down';
        }

        return implode(' ', $classes);
    }

    public function legacyEvents(): bool
    {
        return (bool) config('wire-modals.legacy.events', false);
    }

    public function render(): View
    {
        return view('wire-modals::components.modal');
    }
}
