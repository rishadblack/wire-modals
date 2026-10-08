<?php

namespace Rishadblack\WireModals\Support;

use Rishadblack\WireModals\Exceptions\InvalidPayloadException;

/**
 * The validated, canonical form of what a trigger sends.
 *
 * Both the host and the trait rebuild a Payload from raw client input before acting on
 * it, so every entry point applies the same rules. `toArray()` is the array an action
 * receives: `id`, `data`, `action`, `component`, `modal`, `key` (and `isConfirmed`
 * when a confirmation round-trip set it).
 */
final class Payload
{
    public const COMPONENT_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:\-]*$/';

    public const CLASS_PATTERN = '/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/';

    public const ACTION_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public const DATA_KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public const MAX_DATA_DEPTH = 3;

    public const MAX_DATA_LEAVES = 50;

    /**
     * @param  array<string|int, mixed>  $data
     */
    private function __construct(
        public readonly string $component,
        public readonly ?string $action,
        public readonly int|string|null $id,
        public readonly array $data,
        public readonly ?string $modal,
        public readonly string $key,
        public readonly bool $confirmed,
    ) {}

    /**
     * Build and validate a payload from untrusted input.
     *
     * @param  array<string|int, mixed>  $raw
     *
     * @throws InvalidPayloadException
     */
    public static function fromClient(array $raw): self
    {
        $component = self::stringValue($raw, 'component', 191);

        if ($component === null || $component === '') {
            throw InvalidPayloadException::missing('component');
        }

        $action = self::stringValue($raw, 'action', 100);

        if ($action === null && str_contains($component, '@')) {
            [$component, $action] = explode('@', $component, 2);
        }

        self::assertComponentName($component);

        if ($action === '') {
            $action = null;
        }

        if ($action !== null) {
            self::assertActionName($action);
        }

        $id = self::idValue($raw['id'] ?? null);

        $data = $raw['data'] ?? [];

        if (! is_array($data)) {
            throw InvalidPayloadException::invalid('data', 'must be an array');
        }

        self::assertData($data);

        $modal = self::stringValue($raw, 'modal', 100);

        $key = self::stringValue($raw, 'key', 64);

        if ($key === null || $key === '') {
            $key = $component;
        } elseif (preg_match(self::COMPONENT_PATTERN, $key) !== 1) {
            throw InvalidPayloadException::invalid('key', 'contains characters other than letters, digits, "_", "-", "." and ":"');
        }

        return new self(
            component: $component,
            action: $action,
            id: $id,
            data: $data,
            modal: $modal === '' ? null : $modal,
            key: $key,
            confirmed: (bool) ($raw['isConfirmed'] ?? false),
        );
    }

    public function withAction(string $action): self
    {
        return new self($this->component, $action, $this->id, $this->data, $this->modal, $this->key, $this->confirmed);
    }

    /**
     * The array an action method receives (same keys as the historical Sylloo payload).
     *
     * @return array{id: int|string|null, data: array<string|int, mixed>, action: string|null, component: string, modal: string|null, key: string, isConfirmed?: bool}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'data' => $this->data,
            'action' => $this->action,
            'component' => $this->component,
            'modal' => $this->modal,
            'key' => $this->key,
        ];

        if ($this->confirmed) {
            $payload['isConfirmed'] = true;
        }

        return $payload;
    }

    /**
     * A DOM-safe version of the key, used for wire:key values.
     */
    public function slug(): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->key);
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function assertComponentName(string $component): void
    {
        if (str_contains($component, '\\')) {
            if (! config('wire-modals.allow_class_names', false)) {
                throw InvalidPayloadException::invalid('component', 'is a class name and wire-modals.allow_class_names is off');
            }

            if (preg_match(self::CLASS_PATTERN, $component) !== 1) {
                throw InvalidPayloadException::invalid('component', 'is not a valid class name');
            }

            return;
        }

        if (preg_match(self::COMPONENT_PATTERN, $component) !== 1) {
            throw InvalidPayloadException::invalid('component', 'contains characters other than letters, digits, "_", "-", "." and ":"');
        }

        // Livewire maps dots to directories when it looks a name up on disk: never let a name
        // walk upwards or start/end with a separator.
        if (str_contains($component, '..') || preg_match('/^[.:\-]|[.:\-]$/', $component) === 1) {
            throw InvalidPayloadException::invalid('component', 'is malformed');
        }
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function assertActionName(string $action): void
    {
        if (str_starts_with($action, '__') || preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw InvalidPayloadException::invalid('action', 'is not a valid method name');
        }
    }

    /**
     * @param  array<string|int, mixed>  $data
     *
     * @throws InvalidPayloadException
     */
    public static function assertData(array $data): void
    {
        $leaves = 0;

        self::walkData($data, 1, $leaves);

        $encoded = json_encode($data);

        $limit = (int) config('wire-modals.max_payload_bytes', 8192);

        if ($encoded === false || strlen($encoded) > $limit) {
            throw InvalidPayloadException::invalid('data', "is larger than {$limit} bytes");
        }
    }

    /**
     * @param  array<string|int, mixed>  $data
     *
     * @throws InvalidPayloadException
     */
    private static function walkData(array $data, int $depth, int &$leaves): void
    {
        if ($depth > self::MAX_DATA_DEPTH) {
            throw InvalidPayloadException::invalid('data', 'nests deeper than '.self::MAX_DATA_DEPTH.' levels');
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && (str_starts_with($key, '__') || preg_match(self::DATA_KEY_PATTERN, $key) !== 1)) {
                throw InvalidPayloadException::invalid('data', "has an invalid key [{$key}]");
            }

            if (is_array($value)) {
                self::walkData($value, $depth + 1, $leaves);

                continue;
            }

            if ($value !== null && ! is_scalar($value)) {
                throw InvalidPayloadException::invalid('data', 'may only contain scalar values');
            }

            if (++$leaves > self::MAX_DATA_LEAVES) {
                throw InvalidPayloadException::invalid('data', 'has more than '.self::MAX_DATA_LEAVES.' values');
            }
        }
    }

    /**
     * @param  array<string|int, mixed>  $raw
     *
     * @throws InvalidPayloadException
     */
    private static function stringValue(array $raw, string $field, int $maxLength): ?string
    {
        $value = $raw[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw InvalidPayloadException::invalid($field, 'must be a string');
        }

        if (strlen($value) > $maxLength) {
            throw InvalidPayloadException::invalid($field, "is longer than {$maxLength} characters");
        }

        return trim($value);
    }

    /**
     * @throws InvalidPayloadException
     */
    private static function idValue(mixed $id): int|string|null
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_int($id)) {
            return $id;
        }

        if (! is_string($id)) {
            throw InvalidPayloadException::invalid('id', 'must be a string or an integer');
        }

        if (strlen($id) > 191) {
            throw InvalidPayloadException::invalid('id', 'is longer than 191 characters');
        }

        return $id;
    }
}
