<?php

namespace Rishadblack\WireModals\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * Builds the attributes of a trigger element on the server, so ids and data are escaped
 * by Blade instead of being concatenated into markup by hand.
 *
 * Trigger::make('notes.note-edit', 'openNoteModal')->id($note->id)->with('folder_id', 3)
 * renders:  wire:modal="notes.note-edit" wire:modal.action="openNoteModal" wire:modal.id="7" data-folder-id="3"
 */
final class Trigger implements Htmlable, Stringable
{
    private int|string|null $id = null;

    private ?string $key = null;

    /**
     * @var array<string, scalar|null>
     */
    private array $data = [];

    private function __construct(
        private readonly string $component,
        private readonly ?string $action,
    ) {}

    public static function make(string $component, ?string $action = null): self
    {
        Payload::assertComponentName($component);

        if ($action !== null) {
            Payload::assertActionName($action);
        }

        return new self($component, $action);
    }

    public function id(int|string|null $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Instance key for the host. Two triggers with different keys mount two instances of
     * the same component.
     */
    public function key(?string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function data(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->with((string) $key, $value);
        }

        return $this;
    }

    public function with(string $key, int|string|float|bool|null $value): self
    {
        $this->data[Str::snake($key)] = $value;

        return $this;
    }

    /**
     * The attribute => value pairs of the trigger element.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $payload = Payload::fromClient([
            'component' => $this->component,
            'action' => $this->action,
            'id' => $this->id,
            'key' => $this->key,
            'data' => $this->data,
        ]);

        $attributes = ['wire:modal' => $payload->component];

        if ($payload->action !== null) {
            $attributes['wire:modal.action'] = $payload->action;
        }

        if ($payload->id !== null) {
            $attributes['wire:modal.id'] = (string) $payload->id;
        }

        if ($this->key !== null && $this->key !== '') {
            $attributes['wire:modal.key'] = $payload->key;
        }

        foreach ($payload->data as $key => $value) {
            if (is_array($value)) {
                throw new InvalidArgumentException("Trigger data must be flat; [{$key}] is an array.");
            }

            $attributes['data-'.str_replace('_', '-', (string) $key)] = self::attributeValue($value);
        }

        return $attributes;
    }

    public function toHtml(): string
    {
        $pairs = [];

        foreach ($this->attributes() as $name => $value) {
            $pairs[] = $name.'="'.e($value).'"';
        }

        return implode(' ', $pairs);
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    private static function attributeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
