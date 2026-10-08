<?php

namespace Rishadblack\WireModals\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use Rishadblack\WireModals\Support\Trigger as TriggerBuilder;

/**
 * Renders an element that mounts a component and calls an action when clicked.
 *
 * <x-wire-modals::trigger component="notes.note-edit" action="openNoteModal" :record-id="$note->id" :data="['folder_id' => 3]" class="btn btn-primary">Edit</x-wire-modals::trigger>
 *
 * The record key is `record-id` (not `id`) so a plain HTML `id` attribute still reaches the element.
 */
class Trigger extends Component
{
    public const TAGS = ['button', 'a', 'div', 'span'];

    public TriggerBuilder $trigger;

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        string $component,
        ?string $action = null,
        int|string|null $recordId = null,
        ?string $key = null,
        array $data = [],
        public string $tag = 'button',
    ) {
        if (! in_array($this->tag, self::TAGS, true)) {
            throw new InvalidArgumentException('Trigger tag must be one of: '.implode(', ', self::TAGS).'.');
        }

        $this->trigger = TriggerBuilder::make($component, $action)->id($recordId)->key($key)->data($data);
    }

    /**
     * @return array<string, string>
     */
    public function tagAttributes(): array
    {
        return match ($this->tag) {
            'button' => ['type' => 'button'],
            'a' => ['href' => '#', 'role' => 'button'],
            default => ['role' => 'button', 'tabindex' => '0'],
        };
    }

    public function render(): View
    {
        return view('wire-modals::components.trigger');
    }
}
