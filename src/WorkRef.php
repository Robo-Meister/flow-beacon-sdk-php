<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** An opaque, typed reference to caller-owned work; it never resolves domain state. */
final readonly class WorkRef
{
    public function __construct(public string $type, public string $id)
    {
        self::assertValue($type, 'work_ref.type');
        self::assertValue($id, 'work_ref.id');
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(self::string($value, 'type', 'work_ref'), self::string($value, 'id', 'work_ref'));
    }

    /** @return array{type: string, id: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }

    private static function assertValue(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string.', $field));
        }
    }

    /** @param array<string, mixed> $value */
    private static function string(array $value, string $key, string $parent): string
    {
        if (!isset($value[$key]) || !is_string($value[$key])) {
            throw new InvalidArgumentException(sprintf('%s.%s must be a non-empty string.', $parent, $key));
        }
        return $value[$key];
    }
}
