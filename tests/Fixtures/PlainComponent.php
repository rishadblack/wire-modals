<?php

namespace Rishadblack\WireModals\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A component without the trait: the host must refuse to mount it.
 */
class PlainComponent extends Component
{
    public bool $mounted = false;

    public function mount(): void
    {
        $this->mounted = true;
    }

    public function render(): View
    {
        return view('fixtures::plain');
    }
}
