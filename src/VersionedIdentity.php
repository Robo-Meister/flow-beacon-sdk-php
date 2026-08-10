<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** A distinct key/version identity used for business actions, bindings, and technical tasks. */
final readonly class VersionedIdentity
{
    public function __construct(public string $key, public string $version)
    {
        if (trim($key) === '' || trim($version) === '') {
            throw new InvalidArgumentException('Versioned identity key and version must be non-empty strings.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value, string $field, string $keyName = 'key'): self
    {
        foreach ([$keyName, 'version'] as $key) {
            if (!isset($value[$key]) || !is_string($value[$key]) || trim($value[$key]) === '') {
                throw new InvalidArgumentException(sprintf('%s.%s must be a non-empty string.', $field, $key));
            }
        }
        return new self($value[$keyName], $value['version']);
    }

    /** @return array{key: string, version: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'version' => $this->version];
    }
}
