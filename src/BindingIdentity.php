<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

final readonly class BindingIdentity
{
    public function __construct(public string $id, public string $version)
    {
        if (trim($id) === '' || trim($version) === '') {
            throw new InvalidArgumentException('Binding id and version must be non-empty strings.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        foreach (['id', 'version'] as $key) {
            if (!isset($value[$key]) || !is_string($value[$key]) || trim($value[$key]) === '') {
                throw new InvalidArgumentException(sprintf('binding.%s must be a non-empty string.', $key));
            }
        }
        return new self($value['id'], $value['version']);
    }

    /** @return array{id: string, version: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'version' => $this->version];
    }
}
