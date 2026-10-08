<?php

namespace Rishadblack\WireModals\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Rishadblack\WireModals\Traits\WithModals;

/**
 * Pattern-only component: openTagModal matches open*Modal, nothing carries #[ModalAction].
 */
class TagEdit extends Component
{
    use WithModals;

    public ?string $tag_id = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function openTagModal(array $data = []): void
    {
        $this->tag_id = isset($data['id']) ? (string) $data['id'] : null;

        $this->modalOpen('TagModal');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function tagDelete(array $data = []): void
    {
        $this->tag_id = 'deleted';
    }

    public function render(): View
    {
        return view('fixtures::tag-edit');
    }
}
