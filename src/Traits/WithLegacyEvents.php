<?php

namespace Rishadblack\WireModals\Traits;

use Livewire\Attributes\On;

/**
 * Historical event names kept for applications that migrated from a hand-rolled loader
 * (the Sylloo ERP WithDynamicComponent trait). Use it next to WithModals:
 *
 *     trait WithDynamicComponent { use WithModals, WithLegacyEvents; }
 *
 * The SweetAlert confirm round-trip answers with "onConfirmAlert" / "onCancelAlert" and
 * wraps the original payload in "inputAttributes". New code should not use this trait.
 */
trait WithLegacyEvents
{
    /**
     * @param  array<string|int, mixed>  $data
     */
    #[On('onConfirmAlert')]
    public function onConfirmAlert(array $data = []): void
    {
        $this->runModalAction($this->unwrapLegacyPayload($data));
    }

    /**
     * @param  array<string|int, mixed>  $data
     */
    #[On('onCancelAlert')]
    public function onCancelAlert(array $data = []): void
    {
        $this->skipRender();

        $payload = $this->unwrapLegacyPayload($data);

        if (is_string($payload['component'] ?? null) && $payload['component'] !== '') {
            $this->closeComponent($payload['component']);
        }
    }

    /**
     * @param  array<string|int, mixed>  $data
     */
    #[On('alreadyExistsComponent')]
    public function alreadyExistsComponent(array $data = []): void
    {
        $this->runModalAction($this->unwrapLegacyPayload($data));
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    protected function unwrapLegacyPayload(array $data): array
    {
        if (isset($data['inputAttributes']) && is_array($data['inputAttributes'])) {
            return $data['inputAttributes'];
        }

        return $data;
    }
}
