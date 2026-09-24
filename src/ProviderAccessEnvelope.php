<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/**
 * Transient provider-access authorization material for one governed FlowBeacon execution.
 *
 * The envelope is deliberately separate from the signed execution intent: the intent authenticates
 * caller execution identity, while this value supplies provider access selected by Robo Connector.
 * It is not an entitlement, permission, billing receipt, or business-completion claim.
 */
final readonly class ProviderAccessEnvelope
{
    public const CONTRACT_VERSION = 'rc.provider-access.v1';
    public const SOURCE_OWN = 'OWN';
    public const SOURCE_TECHNICAL_SHARE = 'TECHNICAL_SHARE';
    public const CREDENTIAL_API_KEY = 'api_key';

    public function __construct(
        public string $contractVersion,
        public string $provider,
        public string $source,
        public string $consumerOrganisationId,
        public string $userConfigId,
        public ?string $shareId,
        public ?string $usageReceiptId,
        public string $credentialType,
        public string $credentialValue,
    ) {
        if ($contractVersion !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException(sprintf('Unsupported provider access contract version %s.', $contractVersion));
        }

        self::assertIdentifier($provider, 'provider');
        self::assertIdentifier($consumerOrganisationId, 'consumer_org_id');
        self::assertIdentifier($userConfigId, 'user_config_id');

        if (!in_array($source, [self::SOURCE_OWN, self::SOURCE_TECHNICAL_SHARE], true)) {
            throw new InvalidArgumentException('Unsupported provider access source.');
        }

        if ($credentialType !== self::CREDENTIAL_API_KEY) {
            throw new InvalidArgumentException('Unsupported provider credential type.');
        }

        if (trim($credentialValue) === '' || strlen($credentialValue) > 16384) {
            throw new InvalidArgumentException('Provider credential value must be non-empty.');
        }

        if ($source === self::SOURCE_OWN) {
            if ($shareId !== null || $usageReceiptId !== null) {
                throw new InvalidArgumentException('OWN provider access must not carry share usage identity.');
            }
        } else {
            self::assertIdentifier((string) $shareId, 'share_id');
            self::assertIdentifier((string) $usageReceiptId, 'usage_receipt_id');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        foreach (['version', 'provider', 'source', 'consumer_org_id', 'user_config_id'] as $field) {
            if (!isset($value[$field]) || !is_string($value[$field])) {
                throw new InvalidArgumentException(sprintf('%s is required.', $field));
            }
        }

        $credential = $value['credential'] ?? null;
        if (!is_array($credential) || !is_string($credential['type'] ?? null) || !is_string($credential['value'] ?? null)) {
            throw new InvalidArgumentException('credential must contain string type and value.');
        }

        foreach (['share_id', 'usage_receipt_id'] as $field) {
            if (array_key_exists($field, $value) && $value[$field] !== null && !is_string($value[$field])) {
                throw new InvalidArgumentException(sprintf('%s must be a string or null.', $field));
            }
        }

        return new self(
            $value['version'],
            $value['provider'],
            $value['source'],
            $value['consumer_org_id'],
            $value['user_config_id'],
            $value['share_id'] ?? null,
            $value['usage_receipt_id'] ?? null,
            $credential['type'],
            $credential['value'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->contractVersion,
            'provider' => $this->provider,
            'source' => $this->source,
            'consumer_org_id' => $this->consumerOrganisationId,
            'user_config_id' => $this->userConfigId,
            'share_id' => $this->shareId,
            'usage_receipt_id' => $this->usageReceiptId,
            'credential' => [
                'type' => $this->credentialType,
                'value' => $this->credentialValue,
            ],
        ];
    }

    /** @return array<string, string|null> */
    public function safeDiagnostics(): array
    {
        return [
            'version' => $this->contractVersion,
            'provider' => $this->provider,
            'source' => $this->source,
            'consumer_org_id' => $this->consumerOrganisationId,
            'user_config_id' => $this->userConfigId,
            'share_id' => $this->shareId,
            'usage_receipt_id' => $this->usageReceiptId,
            'credential_type' => $this->credentialType,
        ];
    }

    public function assertMatchesExecutionIntent(ExecutionIntentContext $intent): void
    {
        if (!hash_equals($intent->organisationId, $this->consumerOrganisationId)) {
            throw new InvalidArgumentException('Provider access consumer organisation does not match execution intent.');
        }
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (trim($value) === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException(sprintf('%s is malformed.', $field));
        }
    }
}
