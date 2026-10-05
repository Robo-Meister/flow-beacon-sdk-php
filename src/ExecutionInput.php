<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Single-step transport data. The caller owns instructions; resources are user data. */
final readonly class ExecutionInput
{
    public const SCHEMA = 'flowbeacon.execution-input.v1';
    public const MAX_CONTENT_BYTES = 262144;

    /** @param list<array{role:string,content:string}> $messages */
    private function __construct(public array $messages) {}

    public static function fromArray(array $value): self
    {
        $keys = array_keys($value);
        sort($keys);
        if ($keys !== ['messages', 'schema'] || $value['schema'] !== self::SCHEMA
            || !is_array($value['messages']) || !array_is_list($value['messages']) || count($value['messages']) !== 2) {
            throw new InvalidArgumentException('Unsupported execution-input contract.');
        }
        $size = 0;
        foreach ($value['messages'] as $index => $message) {
            if (!is_array($message)) {
                throw new InvalidArgumentException('Malformed execution-input message.');
            }
            $keys = array_keys($message);
            sort($keys);
            if ($keys !== ['content', 'role']
                || !in_array($message['role'], $index === 0 ? ['system', 'developer'] : ['user'], true)
                || !is_string($message['content']) || trim($message['content']) === ''
                || preg_match('//u', $message['content']) !== 1) {
                throw new InvalidArgumentException('Malformed execution-input message.');
            }
            $size += strlen($message['content']);
        }
        if ($size > self::MAX_CONTENT_BYTES) {
            throw new InvalidArgumentException('Execution input exceeds its size limit.');
        }
        return new self($value['messages']);
    }

    public function toArray(): array
    {
        return ['schema' => self::SCHEMA, 'messages' => $this->messages];
    }
}
