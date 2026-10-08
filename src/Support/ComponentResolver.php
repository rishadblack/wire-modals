<?php

namespace Rishadblack\WireModals\Support;

use Illuminate\Support\Str;
use Rishadblack\WireModals\Exceptions\ComponentNotAllowedException;
use Rishadblack\WireModals\Traits\WithModals;
use Throwable;

/**
 * Resolves a Livewire component name to its class without instantiating it, and decides
 * whether the host may mount it.
 */
final class ComponentResolver
{
    /**
     * @param  array<int, string>  $allowedPatterns
     */
    public function __construct(
        private readonly array $allowedPatterns,
        private readonly bool $allowClassNames,
    ) {}

    public static function make(): self
    {
        $patterns = config('wire-modals.allowed_components', ['*']);

        return new self(
            array_values(array_filter(is_array($patterns) ? $patterns : [], 'is_string')),
            (bool) config('wire-modals.allow_class_names', false),
        );
    }

    /**
     * The class registered under a component name, or null when Livewire does not know it.
     *
     * @return class-string|null
     */
    public function classFor(string $component): ?string
    {
        try {
            $registry = 'Livewire\\Mechanisms\\ComponentRegistry';

            if (class_exists($registry)) {
                // Livewire 3
                /** @var object $instance */
                $instance = app($registry);

                $class = $instance->getClass($component);
            } elseif (app()->bound('livewire.factory')) {
                // Livewire 4
                /** @var object $factory */
                $factory = app('livewire.factory');

                $class = $factory->resolveComponentClass($component);
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return is_string($class) && class_exists($class) ? $class : null;
    }

    /**
     * @return class-string
     *
     * @throws ComponentNotAllowedException
     */
    public function assertAllowed(string $component): string
    {
        if (str_contains($component, '\\') && ! $this->allowClassNames) {
            throw ComponentNotAllowedException::classNamesDisabled($component);
        }

        if (! Str::is($this->allowedPatterns, $component)) {
            throw ComponentNotAllowedException::notListed($component);
        }

        $class = $this->classFor($component);

        if ($class === null) {
            throw ComponentNotAllowedException::unknown($component);
        }

        if (! in_array(WithModals::class, class_uses_recursive($class), true)) {
            throw ComponentNotAllowedException::missingTrait($component);
        }

        return $class;
    }
}
