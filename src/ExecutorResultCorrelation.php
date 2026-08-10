<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Technical executor result correlation only; status never represents business completion. */
final readonly class ExecutorResultCorrelation
{
    public function __construct(
        public string $callerInvocationId,
        public string $callerIntentId,
        public string $correlationId,
        public string $status,
        public ?string $flowBeaconExecutionId = null,
        public ?string $providerId = null,
        public ?string $modelId = null,
    ) {
        foreach (get_object_vars($this) as $field => $value) {
            if (($value !== null && (!is_string($value) || trim($value) === '')) || (is_string($value) && strlen($value) > 255)) {
                throw new InvalidArgumentException(sprintf('%s must be a non-empty string when supplied.', $field));
            }
        }
        if ($flowBeaconExecutionId !== null && hash_equals($callerInvocationId, $flowBeaconExecutionId)) {
            throw new InvalidArgumentException('FlowBeacon execution ID must remain separate from caller invocation ID.');
        }
    }

    public static function fromIntent(ExecutionIntentContext $intent, string $status, ?string $flowBeaconExecutionId = null, ?string $providerId = null, ?string $modelId = null): self
    {
        return new self($intent->invocationId, $intent->intentId, $intent->correlationId, $status, $flowBeaconExecutionId, $providerId, $modelId);
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        foreach (['caller_invocation_id', 'caller_intent_id', 'correlation_id', 'status'] as $field) {
            if (!isset($value[$field]) || !is_string($value[$field])) {
                throw new InvalidArgumentException(sprintf('%s is required.', $field));
            }
        }
        return new self($value['caller_invocation_id'], $value['caller_intent_id'], $value['correlation_id'], $value['status'], $value['flowbeacon_execution_id'] ?? null, $value['provider_id'] ?? null, $value['model_id'] ?? null);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'caller_invocation_id' => $this->callerInvocationId, 'caller_intent_id' => $this->callerIntentId,
            'correlation_id' => $this->correlationId, 'status' => $this->status,
            'flowbeacon_execution_id' => $this->flowBeaconExecutionId, 'provider_id' => $this->providerId,
            'model_id' => $this->modelId,
        ], static fn (?string $v): bool => $v !== null);
    }
}
