<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Transport contract only. A completed output grants no business authority. */
final readonly class ExecutionResult
{
    public const SCHEMA = 'flowbeacon.execution-result.v1';
    private const PENDING = ['pending', 'reserved', 'queued', 'started', 'running'];
    private const SUCCESS = ['succeeded', 'completed'];

    private function __construct(
        public ExecutorResultCorrelation $correlation,
        public ?string $outputSchema,
        public ?array $outputData,
        public ?array $error,
        public bool $replayed,
    ) {}

    /** Parse a decoded wire object. No LLM text extraction or domain validation. */
    public static function fromArray(array $value): self
    {
        if (($value['schema'] ?? null) !== self::SCHEMA
            || !is_array($value['correlation'] ?? null)
            || !array_key_exists('output', $value)
            || !array_key_exists('error', $value)
            || ($value['artifacts'] ?? null) !== []
            || (isset($value['replayed']) && !is_bool($value['replayed']))) {
            throw new InvalidArgumentException('Unsupported or malformed execution-result envelope.');
        }
        $correlation = ExecutorResultCorrelation::fromArray($value['correlation']);
        if ($correlation->flowBeaconExecutionId === null
            || !in_array($correlation->status, [...self::PENDING, ...self::SUCCESS, 'failed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Execution result requires a remote identity and known status.');
        }
        $success = in_array($correlation->status, self::SUCCESS, true);
        $failure = in_array($correlation->status, ['failed', 'cancelled'], true);
        $output = $value['output'];
        $error = $value['error'];
        if ($success) {
            if (!is_array($output) || !is_string($output['schema'] ?? null)
                || trim($output['schema']) === '' || strlen($output['schema']) > 255
                || !is_array($output['data'] ?? null)
                || ($output['data']['schema'] ?? null) !== $output['schema'] || $error !== null) {
                throw new InvalidArgumentException('Completed execution requires a structured schema-bound output.');
            }
            if (strlen(json_encode($output['data'], JSON_THROW_ON_ERROR)) > 262144) {
                throw new InvalidArgumentException('Execution output exceeds its size limit.');
            }
        } elseif ($output !== null) {
            throw new InvalidArgumentException('Pending or failed execution cannot expose an applicable output.');
        }
        if ($failure) {
            if (!is_array($error) || !is_string($error['code'] ?? null) || trim($error['code']) === ''
                || strlen($error['code']) > 255 || !is_string($error['category'] ?? null)
                || trim($error['category']) === '' || strlen($error['category']) > 255
                || !is_bool($error['retryable'] ?? null)) {
                throw new InvalidArgumentException('Failed execution requires a typed error.');
            }
        } elseif ($error !== null) {
            throw new InvalidArgumentException('Only a failed execution may contain an error.');
        }

        return new self($correlation, $output['schema'] ?? null, $output['data'] ?? null, $error, $value['replayed'] ?? false);
    }

    public function isPending(): bool { return in_array($this->correlation->status, self::PENDING, true); }
    public function isCompleted(): bool { return in_array($this->correlation->status, self::SUCCESS, true); }
    public function isFailed(): bool { return !$this->isPending() && !$this->isCompleted(); }

    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'correlation' => $this->correlation->toArray(),
            'output' => $this->outputSchema === null ? null : ['schema' => $this->outputSchema, 'data' => $this->outputData],
            'artifacts' => [],
            'error' => $this->error,
            'replayed' => $this->replayed,
        ];
    }
}
