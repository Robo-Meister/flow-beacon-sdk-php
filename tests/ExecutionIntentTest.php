<?php

declare(strict_types=1);

namespace Robo\AuthSdk\Tests;

use Firebase\JWT\JWT;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robo\AuthSdk\ExecutorResultCorrelation;
use Robo\AuthSdk\ExecutionIntentContext;
use Robo\AuthSdk\RoboAuthSdk;
use RuntimeException;

final class ExecutionIntentTest extends TestCase
{
    private string $privateKey;
    private string $jwksFile;
    private RoboAuthSdk $sdk;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $this->jwksFile = (string) tempnam(sys_get_temp_dir(), 'intent-jwks-');
        file_put_contents($this->jwksFile, json_encode(['keys' => [[
            'kty' => 'RSA', 'kid' => 'test', 'use' => 'sig', 'alg' => 'RS256',
            'n' => self::base64UrlEncode($details['rsa']['n']), 'e' => self::base64UrlEncode($details['rsa']['e']),
        ]]], JSON_THROW_ON_ERROR));
        $this->sdk = new RoboAuthSdk('issuer', 'audience', $this->jwksFile, 60);
    }

    protected function tearDown(): void
    {
        @unlink($this->jwksFile);
    }

    public function testValidV2VerifiesWithOptionalWorkspaceAbsent(): void
    {
        $intent = $this->sdk->verifyExecutionIntent($this->sign($this->claims()), 'org-1');
        self::assertInstanceOf(ExecutionIntentContext::class, $intent);
        self::assertNull($intent->workspaceId);
        self::assertSame('document.revision.propose', $intent->businessAction->key);
        self::assertSame('revision-model-v1', $intent->technicalTask->key);
    }

    public function testWorkspaceCanBePresentButNotEmpty(): void
    {
        $claims = $this->claims();
        $claims['workspace_id'] = 'workspace-7';
        self::assertSame('workspace-7', $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1')->workspaceId);

        $claims['workspace_id'] = '';
        $this->expectException(InvalidArgumentException::class);
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    public function testWrongOrganisationIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('org_id mismatch');
        $this->sdk->verifyExecutionIntent($this->sign($this->claims()), 'other-org');
    }

    public function testMissingIssuerIsRejected(): void
    {
        $claims = $this->claims();
        unset($claims['iss']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('issuer');
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    public function testWrongIssuerIsRejected(): void
    {
        $claims = $this->claims();
        $claims['iss'] = 'other-issuer';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('issuer');
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    public function testExpiredCustomTimingIsRejected(): void
    {
        $claims = $this->claims();
        $claims['issued_at'] = time() - 20;
        $claims['expires_at'] = time() - 10;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    public function testCustomExpiryAtCurrentInstantIsRejected(): void
    {
        $claims = $this->claims();
        $claims['issued_at'] = time() - 1;
        $claims['expires_at'] = time();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    #[DataProvider('invalidClaims')]
    public function testMalformedContractsAreRejected(callable $mutate): void
    {
        $claims = $this->claims();
        $mutate($claims);
        $this->expectException(InvalidArgumentException::class);
        $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1');
    }

    /** @return iterable<string, array{callable(array<string, mixed>&): void}> */
    public static function invalidClaims(): iterable
    {
        yield 'missing invocation ID' => [static function (array &$c): void { unset($c['invocation_id']); }];
        yield 'missing WorkRef' => [static function (array &$c): void { unset($c['work_ref']); }];
        yield 'malformed WorkRef' => [static function (array &$c): void { $c['work_ref']['id'] = ''; }];
        yield 'missing business version' => [static function (array &$c): void { unset($c['business_action']['version']); }];
        yield 'missing technical key' => [static function (array &$c): void { unset($c['technical_task']['key']); }];
        yield 'missing binding version' => [static function (array &$c): void { unset($c['binding']['version']); }];
        yield 'missing idempotency' => [static function (array &$c): void { unset($c['idempotency_key']); }];
        yield 'malformed digest' => [static function (array &$c): void { $c['source']['digest'] = 'not-a-digest'; }];
        yield 'malformed correlation' => [static function (array &$c): void { $c['correlation_id'] = "bad\nvalue"; }];
        yield 'future version' => [static function (array &$c): void { $c['contract_version'] = '3'; }];
    }

    public function testStandardJwtTimingClaimsAreSupported(): void
    {
        $claims = $this->claims();
        $claims['iat'] = $claims['issued_at'];
        $claims['exp'] = $claims['expires_at'];
        unset($claims['issued_at'], $claims['expires_at']);
        self::assertSame($claims['iat'], $this->sdk->verifyExecutionIntent($this->sign($claims), 'org-1')->issuedAt);
    }

    public function testResultCorrelationRoundTripsSeparateIdentities(): void
    {
        $intent = $this->sdk->verifyExecutionIntent($this->sign($this->claims()), 'org-1');
        $result = ExecutorResultCorrelation::fromIntent($intent, 'succeeded', 'fb-execution-9', 'safe-provider', 'safe-model');
        $roundTrip = ExecutorResultCorrelation::fromArray($result->toArray());
        self::assertSame('rc-invocation-1', $roundTrip->callerInvocationId);
        self::assertSame('fb-execution-9', $roundTrip->flowBeaconExecutionId);
        self::assertNotSame($roundTrip->callerInvocationId, $roundTrip->flowBeaconExecutionId);
        self::assertSame('correlation-1', $roundTrip->correlationId);
    }

    public function testRemoteExecutionCannotAliasCallerInvocation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExecutorResultCorrelation('same-id', 'intent', 'correlation', 'succeeded', 'same-id');
    }

    public function testTypedIntentHasNoAuthorizationOrBusinessCompletionSemantics(): void
    {
        $intent = $this->sdk->verifyExecutionIntent($this->sign($this->claims()), 'org-1');
        $fields = array_keys(get_object_vars($intent));

        self::assertNotContains('permissionAllowed', $fields);
        self::assertNotContains('riskAccepted', $fields);
        self::assertNotContains('qualityPassed', $fields);
        self::assertNotContains('businessComplete', $fields);
        self::assertStringContainsString('not proof of permission', (string) (new \ReflectionClass($intent))->getDocComment());
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return [
            'iss' => 'issuer', 'contract_version' => '2', 'intent_id' => 'intent-1', 'invocation_id' => 'rc-invocation-1',
            'org_id' => 'org-1', 'work_ref' => ['type' => 'document', 'id' => 'work-42'],
            'business_action' => ['key' => 'document.revision.propose', 'version' => '2026-07-12'],
            'binding' => ['id' => 'binding-1', 'version' => '4'],
            'technical_task' => ['key' => 'revision-model-v1', 'version' => '2026-07-12.1'],
            'source' => ['type' => 'template', 'ref' => 'template-5', 'version' => '3', 'digest' => str_repeat('a', 64)],
            'correlation_id' => 'correlation-1', 'causation_id' => 'cause-1', 'execution_root_id' => 'root-1',
            'parent_execution_id' => 'parent-1', 'idempotency_key' => 'idem-1',
            'issued_at' => time(), 'expires_at' => time() + 300, 'decision_refs' => ['decision:opaque:1'],
        ];
    }

    /** @param array<string, mixed> $claims */
    private function sign(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', 'test');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
