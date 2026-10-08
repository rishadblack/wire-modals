<?php

namespace Rishadblack\WireModals\Exceptions;

final class ActionNotAllowedException extends WireModalsException
{
    public static function missing(string $component): self
    {
        return new self("No action was given for [{$component}] and it declares no #[ModalAction(default: true)] method.");
    }

    public static function denied(string $action, string $component): self
    {
        return new self("Action [{$action}] on [{$component}] is not callable: mark it with #[ModalAction] or match wire-modals.action_patterns.");
    }

    public static function unauthorized(string $action, string $component): self
    {
        return new self("Action [{$action}] on [{$component}] was refused by authorizeModalAction().");
    }
}
