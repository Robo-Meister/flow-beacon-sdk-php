<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Safe source metadata only; protected source content does not belong in an intent. */
final readonly class SourceContext
{
    public function __construct(
        public string $version,
        public string $digest,
        public ?string $ref = null,
        public ?string $type = null,
    ) {
        if (trim($version) === '') {
            throw new InvalidArgumentException('source.version must be a non-empty string.');
        }
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $digest)) {
            throw new InvalidArgumentException('source.digest must be a 64-character SHA-256 hex digest.');
        }
        foreach (['ref' => $ref, 'type' => $type] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf('source.%s cannot be empty.', $field));
            }
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        foreach (['version', 'digest'] as $key) {
            if (!isset($value[$key]) || !is_string($value[$key])) {
                throw new InvalidArgumentException(sprintf('source.%s must be a string.', $key));
            }
        }
        foreach (['ref', 'type'] as $key) {
            if (isset($value[$key]) && !is_string($value[$key])) {
                throw new InvalidArgumentException(sprintf('source.%s must be a string when supplied.', $key));
            }
        }
        return new self($value['version'], $value['digest'], $value['ref'] ?? null, $value['type'] ?? null);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter(['ref' => $this->ref, 'type' => $this->type, 'version' => $this->version, 'digest' => $this->digest], static fn (?string $v): bool => $v !== null);
    }
}
