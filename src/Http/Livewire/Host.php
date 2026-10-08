<?php

namespace Rishadblack\WireModals\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Rishadblack\WireModals\Exceptions\ActionNotAllowedException;
use Rishadblack\WireModals\Exceptions\ComponentNotAllowedException;
use Rishadblack\WireModals\Exceptions\WireModalsException;
use Rishadblack\WireModals\Support\ActionResolver;
use Rishadblack\WireModals\Support\ComponentResolver;
use Rishadblack\WireModals\Support\Payload;

/**
 * Keeps the on-demand components alive. Placed once per layout.
 *
 * A trigger calls load() with its payload. The first time a component (or key) is seen it
 * is mounted with the payload; afterwards the payload is forwarded to the existing instance
 * through the "wire-modals:already-mounted" event. unmount() drops an entry once its modal
 * has closed.
 */
class Host extends Component
{
    /**
     * Mounted entries keyed by payload key. Locked: only load() and unmount() may change it.
     *
     * @var array<string, array<string, mixed>>
     */
    #[Locked]
    public array $components = [];

    /**
     * @param  array<string|int, mixed>  $payload
     */
    #[On('wire-modals:load')]
    public function load(array $payload = []): void
    {
        try {
            $validated = Payload::fromClient($payload);

            $class = ComponentResolver::make()->assertAllowed($validated->component);

            // Refuse unknown actions before mounting anything, so a bad trigger never leaves
            // a component mounted with nothing to show. The trait checks again on its side.
            $resolver = ActionResolver::make();
            $action = $validated->action ?? $resolver->defaultAction($class);

            if ($action === null) {
                throw ActionNotAllowedException::missing($validated->component);
            }

            $canonical = $resolver->resolve($class, $action);

            if ($canonical === null) {
                throw ActionNotAllowedException::denied($action, $validated->component);
            }

            $validated = $validated->withAction($canonical);

            if (isset($this->components[$validated->key])) {
                $this->skipRender();

                $this->dispatch('wire-modals:already-mounted', payload: $validated->toArray())->to($validated->component);

                return;
            }

            $limit = (int) config('wire-modals.max_mounted', 15);

            if (count($this->components) >= $limit) {
                throw ComponentNotAllowedException::limitReached($validated->component, $limit);
            }
        } catch (WireModalsException $exception) {
            $this->reject($exception, $payload);

            return;
        }

        $this->components[$validated->key] = $validated->toArray();
    }

    /**
     * Drop every entry of a component. Called by the host's Alpine component when a modal
     * that owns its component's lifetime has closed, or through closeComponent().
     */
    #[On('wire-modals:unmount')]
    public function unmount(string $target = ''): void
    {
        $remaining = array_filter(
            $this->components,
            fn (array $entry): bool => ($entry['component'] ?? null) !== $target,
        );

        if (count($remaining) === count($this->components)) {
            $this->skipRender();

            return;
        }

        $this->components = $remaining;
    }

    /**
     * Legacy entry point: the "loadComponent" Livewire event with the historical signature.
     *
     * @param  array<string|int, mixed>|mixed  $data
     */
    public function loadComponent(mixed $action = null, mixed $component = null, mixed $modal = null, mixed $id = null, mixed $data = []): void
    {
        $this->load([
            'action' => $action,
            'component' => $component,
            'modal' => $modal,
            'id' => $id,
            'data' => is_array($data) ? $data : [],
        ]);
    }

    /**
     * Legacy entry point: the "closeComponent" Livewire event.
     */
    public function closeComponent(mixed $component = null): void
    {
        $this->unmount(is_string($component) ? $component : '');
    }

    public function render(): View
    {
        $entries = [];

        foreach ($this->components as $key => $entry) {
            $entries[] = [
                'component' => (string) $entry['component'],
                'slug' => (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $key),
                'payload' => $entry,
            ];
        }

        return view('wire-modals::host', [
            'entries' => $entries,
            'legacyEvents' => (bool) config('wire-modals.legacy.events', false),
            'legacyDirective' => (bool) config('wire-modals.legacy.directive', false),
            'dataExclusions' => implode(',', array_filter((array) config('wire-modals.data_exclusions', []), 'is_string')),
        ]);
    }

    /**
     * Legacy event names are only listened to when enabled in config.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        if (! config('wire-modals.legacy.events', false)) {
            return [];
        }

        return [
            'loadComponent' => 'loadComponent',
            'closeComponent' => 'closeComponent',
        ];
    }

    /**
     * @param  array<string|int, mixed>  $payload
     */
    protected function reject(WireModalsException $exception, array $payload): void
    {
        $this->skipRender();

        // These two strings failed validation, so they are untrusted: strip control characters
        // and cap their length before they reach the log or the browser event.
        $component = self::printable($payload['component'] ?? null);
        $action = self::printable($payload['action'] ?? null);

        if (config('wire-modals.log_rejections', true)) {
            Log::warning('wire-modals: '.$exception->getMessage(), [
                'component' => $component,
                'action' => $action,
            ]);
        }

        // "component" is a reserved dispatch parameter in Livewire (it targets the event), hence "source".
        $this->dispatch('wire-modals:rejected', source: $component, action: $action, reason: $exception->getMessage());
    }

    private static function printable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = (string) preg_replace('/[^\x20-\x7E]/', '', $value);

        return mb_substr($clean, 0, 120);
    }
}
