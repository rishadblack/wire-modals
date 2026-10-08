<?php

namespace Rishadblack\WireModals\Exceptions;

final class ComponentNotAllowedException extends WireModalsException
{
    public static function classNamesDisabled(string $component): self
    {
        return new self("Component [{$component}] is a class name; class names are disabled (wire-modals.allow_class_names).");
    }

    public static function notListed(string $component): self
    {
        return new self("Component [{$component}] does not match wire-modals.allowed_components.");
    }

    public static function unknown(string $component): self
    {
        return new self("Component [{$component}] is not a registered Livewire component.");
    }

    public static function missingTrait(string $component): self
    {
        return new self("Component [{$component}] does not use the WithModals trait.");
    }

    public static function limitReached(string $component, int $limit): self
    {
        return new self("Component [{$component}] was not mounted: the host already holds {$limit} components (wire-modals.max_mounted).");
    }
}
