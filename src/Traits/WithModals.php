<?php

namespace Rishadblack\WireModals\Traits;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Rishadblack\WireModals\Exceptions\ActionNotAllowedException;
use Rishadblack\WireModals\Exceptions\WireModalsException;
use Rishadblack\WireModals\Support\ActionResolver;
use Rishadblack\WireModals\Support\Payload;
use Rishadblack\WireModals\Support\Trigger;

/**
 * Makes a Livewire component mountable by the wire-modals host and lets it drive
 * Bootstrap modals declared in its own view.
 *
 * Actions are public methods named open*Modal (configurable) or marked #[ModalAction].
 * They receive one array: id, data, action, component, modal, key.
 */
trait WithModals
{
    /**
     * @var array<class-string, string>
     */
    protected static array $wireModalsComponentNames = [];

    /**
     * Livewire mount hook: the host passes the trigger payload as a mount parameter.
     *
     * @param  array<string|int, mixed>  $wireModalsPayload
     */
    public function mountWithModals(array $wireModalsPayload = []): void
    {
        if ($wireModalsPayload === []) {
            return;
        }

        // A component mounted for an action that is refused has nothing to show: unmount it
        // again instead of leaving an invisible instance in the host.
        if (! $this->runModalAction($wireModalsPayload)) {
            $this->closeComponent();
        }
    }

    /**
     * Re-entry: the host forwards a payload to an instance that is already mounted.
     *
     * @param  array<string|int, mixed>  $payload
     */
    #[On('wire-modals:already-mounted')]
    public function onWireModalsAlreadyMounted(array $payload = []): void
    {
        $this->runModalAction($payload);
    }

    /**
     * Show a modal declared in this component's view. By default the component is
     * unmounted when that modal closes; pass $keepComponent for sub-modals.
     */
    public function modalOpen(string $modal, bool $keepComponent = false): void
    {
        // "component" is a reserved dispatch parameter in Livewire (it targets the event), hence "owner".
        $this->dispatch('wire-modals:open', modal: $modal, owner: $keepComponent ? null : $this->getName());
    }

    public function modalClose(string $modal): void
    {
        $this->dispatch('wire-modals:close', modal: $modal);
    }

    /**
     * Ask the host to unmount this component (or another one by name). Open modals of that
     * component are hidden first so Bootstrap can clean up.
     */
    public function closeComponent(?string $component = null): void
    {
        $this->dispatch('wire-modals:dispose', target: $component ?? $this->getName());
    }

    /**
     * A trigger builder pre-filled with this component's name.
     */
    public static function modalTrigger(?string $action = null): Trigger
    {
        return Trigger::make(static::wireModalsComponentName(), $action);
    }

    /**
     * Validate a payload, resolve and authorise its action, then call it.
     *
     * @param  array<string|int, mixed>  $payload
     * @return bool Whether the action was called
     */
    protected function runModalAction(array $payload): bool
    {
        $requested = is_string($payload['action'] ?? null) ? $payload['action'] : null;

        try {
            $validated = Payload::fromClient($payload);

            $resolver = ActionResolver::make();

            $requested = $validated->action ?? $resolver->defaultAction($this);

            if ($requested === null) {
                throw ActionNotAllowedException::missing($validated->component);
            }

            // Canonical spelling from here on: the client's casing never reaches the hook or the call.
            $action = $resolver->resolve($this, $requested);

            if ($action === null) {
                throw ActionNotAllowedException::denied($requested, $validated->component);
            }

            $arguments = $validated->withAction($action)->toArray();

            if (! $this->authorizeModalAction($action, $arguments)) {
                throw ActionNotAllowedException::unauthorized($action, $validated->component);
            }
        } catch (WireModalsException $exception) {
            $this->rejectModalAction($exception, $requested);

            return false;
        }

        $this->{$action}($arguments);

        return true;
    }

    /**
     * Hook for the application's permission checks. Return false to refuse the call.
     *
     * @param  array{id: int|string|null, data: array<string|int, mixed>, action: string|null, component: string, modal: string|null, key: string, isConfirmed?: bool}  $payload
     */
    protected function authorizeModalAction(string $action, array $payload): bool
    {
        return true;
    }

    protected function rejectModalAction(WireModalsException $exception, ?string $action): void
    {
        if (config('wire-modals.log_rejections', true)) {
            Log::warning('wire-modals: '.$exception->getMessage(), [
                'component' => $this->getName(),
                'action' => $action,
            ]);
        }

        $this->dispatch('wire-modals:rejected', source: $this->getName(), action: $action, reason: $exception->getMessage());
    }

    protected static function wireModalsComponentName(): string
    {
        return static::$wireModalsComponentNames[static::class]
            ??= app('livewire')->new(static::class)->getName();
    }
}
