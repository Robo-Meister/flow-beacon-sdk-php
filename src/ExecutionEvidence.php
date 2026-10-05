<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Safe execution provenance. Never accepts raw provider payloads or credentials. */
final readonly class ExecutionEvidence
{
    public const SCHEMA = 'flowbeacon.execution-evidence.v1';

    private function __construct(public ?array $profile, public ?array $attempt) {}

    public static function fromArray(array $value): self
    {
        self::keys($value, ['schema', 'input_contract', 'profile', 'attempt']);
        if ($value['schema'] !== self::SCHEMA || $value['input_contract'] !== ExecutionInput::SCHEMA) {
            throw new InvalidArgumentException('Unsupported execution evidence.');
        }
        $profile = $value['profile'];
        if ($profile !== null) {
            if (!is_array($profile)) throw new InvalidArgumentException('Invalid execution profile.');
            self::keys($profile, ['id', 'version', 'provider', 'api', 'model', 'capabilities', 'max_output_tokens', 'store', 'timeout_seconds', 'technical_task', 'output_schema', 'digest']);
            foreach (['id', 'version', 'model'] as $key) self::text($profile[$key]);
            $supportedApis = ['openai' => 'responses', 'anthropic' => 'messages'];
            if (!is_string($profile['provider']) || ($supportedApis[$profile['provider']] ?? null) !== $profile['api'] || $profile['store'] !== false
                || $profile['capabilities'] !== ['structured_outputs']
                || !is_int($profile['max_output_tokens']) || $profile['max_output_tokens'] < 1 || $profile['max_output_tokens'] > 32768
                || !is_int($profile['timeout_seconds']) || $profile['timeout_seconds'] < 1 || $profile['timeout_seconds'] > 120
                || !is_array($profile['output_schema']) || !is_array($profile['technical_task'])
                || !is_string($profile['digest']) || preg_match('/^[a-f0-9]{64}$/D', $profile['digest']) !== 1) {
                throw new InvalidArgumentException('Invalid execution profile.');
            }
            self::keys($profile['technical_task'], ['key', 'version']);
            self::text($profile['technical_task']['key']);
            self::text($profile['technical_task']['version']);
        }
        $attempt = $value['attempt'];
        if ($attempt !== null) {
            if (!is_array($attempt) || $profile === null) throw new InvalidArgumentException('Invalid provider attempt.');
            self::keys($attempt, ['number', 'request_id', 'response_id', 'model', 'status', 'usage']);
            if ($attempt['number'] !== 1 || !in_array($attempt['status'], ['received', 'completed', 'refused', 'incomplete', 'failed', 'rejected', 'outcome_unknown'], true)
                || !is_array($attempt['usage'])) throw new InvalidArgumentException('Invalid provider attempt.');
            foreach (['request_id', 'response_id', 'model'] as $key) {
                if ($attempt[$key] !== null) {
                    self::text($attempt[$key]);
                    if (preg_match('/^[A-Za-z0-9_.:\/-]+$/D', $attempt[$key]) !== 1) throw new InvalidArgumentException('Invalid provider identifier.');
                }
            }
            foreach ($attempt['usage'] as $key => $count) {
                if (!in_array($key, ['input_tokens', 'output_tokens', 'total_tokens'], true) || !is_int($count) || $count < 0) {
                    throw new InvalidArgumentException('Invalid provider usage.');
                }
            }
        }
        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 262144) throw new InvalidArgumentException('Execution evidence exceeds size limit.');
        return new self($profile, $attempt);
    }

    private static function keys(array $value, array $expected): void
    {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);
        if ($keys !== $expected) throw new InvalidArgumentException('Unknown or missing execution-evidence fields.');
    }

    private static function text(mixed $value): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 255 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Invalid execution-evidence text.');
        }
    }

    public function toArray(): array
    {
        return ['schema' => self::SCHEMA, 'input_contract' => ExecutionInput::SCHEMA, 'profile' => $this->profile, 'attempt' => $this->attempt];
    }
}
