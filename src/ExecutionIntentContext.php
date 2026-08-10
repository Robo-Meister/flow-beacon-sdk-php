<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/**
 * Immutable V2 caller execution identity and correlation context.
 *
 * A verified value proves only that Robo Connector issued this exact scoped
 * request. It is not proof of permission, Risk/Quality acceptance, canonical
 * AI output, or business completion.
 */
final readonly class ExecutionIntentContext
{
    public const CONTRACT_VERSION = '2';

    /** @param list<string> $decisionRefs */
    public function __construct(
        public string $contractVersion,
        public string $intentId,
        public string $invocationId,
        public string $organisationId,
        public ?string $workspaceId,
        public WorkRef $workRef,
        public VersionedIdentity $businessAction,
        public BindingIdentity $binding,
        public VersionedIdentity $technicalTask,
        public SourceContext $source,
        public string $correlationId,
        public ?string $causationId,
        public ?string $executionRootId,
        public ?string $parentExecutionId,
        public string $idempotencyKey,
        public int $issuedAt,
        public int $expiresAt,
        public array $decisionRefs = [],
    ) {
        if ($contractVersion !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException(sprintf('Unsupported execution intent contract version %s.', $contractVersion));
        }
        foreach ([
            'intent_id' => $intentId, 'invocation_id' => $invocationId,
            'org_id' => $organisationId, 'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
        ] as $field => $value) {
            self::assertIdentifier($value, $field);
        }
        foreach (['workspace_id' => $workspaceId, 'causation_id' => $causationId, 'execution_root_id' => $executionRootId, 'parent_execution_id' => $parentExecutionId] as $field => $value) {
            if ($value !== null) {
                self::assertIdentifier($value, $field);
            }
        }
        if ($issuedAt <= 0 || $expiresAt <= 0 || $expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Execution intent timing claims are invalid.');
        }
        foreach ($decisionRefs as $ref) {
            if (!is_string($ref) || trim($ref) === '') {
                throw new InvalidArgumentException('decision_refs must contain only non-empty opaque strings.');
            }
        }
    }

    /** @param array<string, mixed> $claims */
    public static function fromClaims(array $claims): self
    {
        $requiredStrings = ['contract_version', 'intent_id', 'invocation_id', 'org_id', 'correlation_id', 'idempotency_key'];
        foreach ($requiredStrings as $field) {
            if (!isset($claims[$field]) || !is_string($claims[$field])) {
                throw new InvalidArgumentException(sprintf('%s must be a non-empty string.', $field));
            }
        }
        foreach (['work_ref', 'business_action', 'binding', 'technical_task', 'source'] as $field) {
            if (!isset($claims[$field]) || !is_array($claims[$field])) {
                throw new InvalidArgumentException(sprintf('%s must be an object.', $field));
            }
        }
        foreach (['workspace_id', 'causation_id', 'execution_root_id', 'parent_execution_id'] as $field) {
            if (array_key_exists($field, $claims) && (!is_string($claims[$field]) || trim($claims[$field]) === '')) {
                throw new InvalidArgumentException(sprintf('%s must be a non-empty string when supplied.', $field));
            }
        }
        $issuedAt = self::timeClaim($claims, 'issued_at', 'iat');
        $expiresAt = self::timeClaim($claims, 'expires_at', 'exp');
        $decisionRefs = $claims['decision_refs'] ?? [];
        if (!is_array($decisionRefs) || !array_is_list($decisionRefs)) {
            throw new InvalidArgumentException('decision_refs must be a list when supplied.');
        }

        return new self(
            $claims['contract_version'], $claims['intent_id'], $claims['invocation_id'], $claims['org_id'],
            $claims['workspace_id'] ?? null, WorkRef::fromArray($claims['work_ref']),
            VersionedIdentity::fromArray($claims['business_action'], 'business_action'),
            BindingIdentity::fromArray($claims['binding']),
            VersionedIdentity::fromArray($claims['technical_task'], 'technical_task'),
            SourceContext::fromArray($claims['source']), $claims['correlation_id'],
            $claims['causation_id'] ?? null, $claims['execution_root_id'] ?? null,
            $claims['parent_execution_id'] ?? null, $claims['idempotency_key'], $issuedAt, $expiresAt,
            $decisionRefs,
        );
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (trim($value) === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException(sprintf('%s is malformed.', $field));
        }
    }

    /** @param array<string, mixed> $claims */
    private static function timeClaim(array $claims, string $custom, string $standard): int
    {
        $value = $claims[$custom] ?? $claims[$standard] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException(sprintf('Missing or invalid %s/%s timing claim.', $custom, $standard));
        }
        return (int) $value;
    }
}
