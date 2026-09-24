<?php

declare(strict_types=1);

namespace Robo\AuthSdk\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Robo\AuthSdk\BindingIdentity;
use Robo\AuthSdk\ExecutionIntentContext;
use Robo\AuthSdk\ProviderAccessEnvelope;
use Robo\AuthSdk\SourceContext;
use Robo\AuthSdk\VersionedIdentity;
use Robo\AuthSdk\WorkRef;

final class ProviderAccessEnvelopeTest extends TestCase
{
    public function testOwnRoundTripsWithoutShareIdentity(): void
    {
        $value = ProviderAccessEnvelope::fromArray([
            'version' => ProviderAccessEnvelope::CONTRACT_VERSION,
            'provider' => 'openai',
            'source' => ProviderAccessEnvelope::SOURCE_OWN,
            'consumer_org_id' => 'org-1',
            'user_config_id' => 'config-1',
            'credential' => ['type' => 'api_key', 'value' => 'sk-test'],
        ]);

        self::assertSame(ProviderAccessEnvelope::SOURCE_OWN, $value->source);
        self::assertNull($value->shareId);
        self::assertNull($value->usageReceiptId);
        self::assertSame('sk-test', $value->toArray()['credential']['value']);
    }

    public function testTechnicalShareRequiresShareAndUsageReceipt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderAccessEnvelope::fromArray([
            'version' => ProviderAccessEnvelope::CONTRACT_VERSION,
            'provider' => 'openai',
            'source' => ProviderAccessEnvelope::SOURCE_TECHNICAL_SHARE,
            'consumer_org_id' => 'org-1',
            'user_config_id' => 'config-1',
            'share_id' => 'share-1',
            'credential' => ['type' => 'api_key', 'value' => 'sk-test'],
        ]);
    }

    public function testTechnicalShareRoundTripsReceiptIdentity(): void
    {
        $value = ProviderAccessEnvelope::fromArray([
            'version' => ProviderAccessEnvelope::CONTRACT_VERSION,
            'provider' => 'openai',
            'source' => ProviderAccessEnvelope::SOURCE_TECHNICAL_SHARE,
            'consumer_org_id' => 'org-1',
            'user_config_id' => 'config-1',
            'share_id' => 'share-1',
            'usage_receipt_id' => 'usage-1',
            'credential' => ['type' => 'api_key', 'value' => 'sk-test'],
        ]);

        self::assertSame('share-1', $value->shareId);
        self::assertSame('usage-1', $value->usageReceiptId);
    }

    public function testSafeDiagnosticsNeverExposeCredentialValue(): void
    {
        $value = ProviderAccessEnvelope::fromArray([
            'version' => ProviderAccessEnvelope::CONTRACT_VERSION,
            'provider' => 'openai',
            'source' => ProviderAccessEnvelope::SOURCE_OWN,
            'consumer_org_id' => 'org-1',
            'user_config_id' => 'config-1',
            'credential' => ['type' => 'api_key', 'value' => 'must-not-leak'],
        ]);

        $diagnostics = $value->safeDiagnostics();

        self::assertArrayNotHasKey('credential', $diagnostics);
        self::assertStringNotContainsString('must-not-leak', json_encode($diagnostics, JSON_THROW_ON_ERROR));
    }

    public function testConsumerOrganisationMustMatchVerifiedExecutionIntent(): void
    {
        $value = ProviderAccessEnvelope::fromArray([
            'version' => ProviderAccessEnvelope::CONTRACT_VERSION,
            'provider' => 'openai',
            'source' => ProviderAccessEnvelope::SOURCE_OWN,
            'consumer_org_id' => 'other-org',
            'user_config_id' => 'config-1',
            'credential' => ['type' => 'api_key', 'value' => 'sk-test'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $value->assertMatchesExecutionIntent($this->intent());
    }

    private function intent(): ExecutionIntentContext
    {
        return new ExecutionIntentContext(
            '2',
            'intent-1',
            'invocation-1',
            'org-1',
            null,
            new WorkRef('document', 'doc-1'),
            new VersionedIdentity('document.review', '1'),
            new BindingIdentity('binding-1', '1'),
            new VersionedIdentity('task-1', '1'),
            new SourceContext('1', str_repeat('a', 64), 'doc-1', 'document'),
            'correlation-1',
            null,
            null,
            null,
            'idem-1',
            100,
            200,
        );
    }
}
