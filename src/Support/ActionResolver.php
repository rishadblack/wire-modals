<?php

namespace Rishadblack\WireModals\Support;

use Illuminate\Support\Str;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use Rishadblack\WireModals\Attributes\ModalAction;
use Rishadblack\WireModals\Traits\WithLegacyEvents;
use Rishadblack\WireModals\Traits\WithModals;

/**
 * Decides which methods a trigger may call on a component.
 *
 * A method is callable when it is public, non-static, declared by the application (not by
 * Livewire or by this package) and either carries #[ModalAction] or matches one of the
 * configured name patterns.
 */
final class ActionResolver
{
    /**
     * @var array<class-string, array<string, true>>
     */
    private static array $deniedCache = [];

    /**
     * @var array<class-string, string|false>
     */
    private static array $defaultCache = [];

    /**
     * Lifecycle and rendering methods a component may declare itself; never callable.
     *
     * @var array<int, string>
     */
    private const RESERVED_METHODS = ['render', 'mount', 'boot', 'booted', 'hydrate', 'dehydrate', 'updating', 'updated', 'rendering', 'rendered', 'exception', 'placeholder'];

    /**
     * @var array<int, string>
     */
    private const RESERVED_PREFIXES = ['hydrate', 'dehydrate', 'updating', 'updated', 'mount', 'boot'];

    /**
     * @param  array<int, string>  $patterns
     */
    public function __construct(private readonly array $patterns) {}

    public static function make(): self
    {
        $patterns = config('wire-modals.action_patterns', ['open*Modal']);

        return new self(array_values(array_filter(is_array($patterns) ? $patterns : [], 'is_string')));
    }

    /**
     * @param  object|class-string  $component  A component instance or its class name
     */
    public function allows(object|string $component, string $action): bool
    {
        return $this->resolve($component, $action) !== null;
    }

    /**
     * The method a trigger may call for the requested action, in its canonical spelling, or
     * null when it may not be called.
     *
     * PHP method names are case-insensitive, so the spelling the client sent is never used
     * again after this point: pattern matching, authorizeModalAction() and the call itself all
     * see the name as declared in the class.
     *
     * @param  object|class-string  $component  A component instance or its class name
     */
    public function resolve(object|string $component, string $action): ?string
    {
        if (str_starts_with($action, '__') || ! method_exists($component, $action)) {
            return null;
        }

        $method = new ReflectionMethod($component, $action);

        if (! $method->isPublic() || $method->isStatic() || $method->isAbstract() || $method->isConstructor()) {
            return null;
        }

        $canonical = $method->getName();
        $name = strtolower($canonical);

        if (isset($this->deniedMethods($component)[$name]) || in_array($name, self::RESERVED_METHODS, true)) {
            return null;
        }

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return null;
            }
        }

        if ($this->attributeFor($method) !== null) {
            return $canonical;
        }

        return Str::is($this->patterns, $canonical) ? $canonical : null;
    }

    /**
     * The method marked #[ModalAction(default: true)], if the component has one.
     */
    public function defaultAction(object|string $component): ?string
    {
        $class = is_object($component) ? $component::class : $component;

        if (! array_key_exists($class, self::$defaultCache)) {
            self::$defaultCache[$class] = false;

            foreach ((new ReflectionClass($component))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($this->attributeFor($method)?->default === true) {
                    self::$defaultCache[$class] = $method->getName();

                    break;
                }
            }
        }

        return self::$defaultCache[$class] === false ? null : self::$defaultCache[$class];
    }

    public function attributeFor(ReflectionMethod $method): ?ModalAction
    {
        $attributes = $method->getAttributes(ModalAction::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * Lower-cased names of every method that can never be an action: everything Livewire's
     * base component exposes, everything from Livewire's own traits, and this package's traits.
     *
     * @return array<string, true>
     */
    private function deniedMethods(object|string $component): array
    {
        $class = is_object($component) ? $component::class : $component;

        if (isset(self::$deniedCache[$class])) {
            return self::$deniedCache[$class];
        }

        $denied = [];

        foreach ((new ReflectionClass(Component::class))->getMethods() as $method) {
            $denied[strtolower($method->getName())] = true;
        }

        foreach (class_uses_recursive($component) as $trait) {
            if (! str_starts_with($trait, 'Livewire\\') && ! in_array($trait, [WithModals::class, WithLegacyEvents::class], true)) {
                continue;
            }

            foreach ((new ReflectionClass($trait))->getMethods() as $method) {
                $denied[strtolower($method->getName())] = true;
            }
        }

        return self::$deniedCache[$class] = $denied;
    }
}
