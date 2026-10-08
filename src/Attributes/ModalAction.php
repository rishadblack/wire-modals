<?php

namespace Rishadblack\WireModals\Attributes;

use Attribute;

/**
 * Marks a public method as callable through a wire-modals trigger, whatever its name.
 *
 * Set `default` on one method per component to make it the action used when a trigger
 * names none. `ability` is passed to authorizeModalAction() untouched for the host
 * application to interpret.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ModalAction
{
    public function __construct(
        public readonly bool $default = false,
        public readonly ?string $ability = null,
    ) {}
}
