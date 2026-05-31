<?php

declare(strict_types=1);

namespace Robo\AuthSdk\Tests;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Robo\AuthSdk\RoboAuthSdk;
use RuntimeException;

final class RoboAuthSdkTest extends TestCase
{
    private const ISSUER = 'https://account.robo.dev';
    private const AUDIENCE = 'flowbeacon';
    private const KEY_ID = 'test-key-1';

    private string $privateKey;
    private string $jwksFile;
    private RoboAuthSdk $sdk;

    protected function setUp(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        self::assertIsString($privateKey);

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertArrayHasKey('rsa', $details);

        $this->privateKey = $privateKey;
        $this->jwksFile = (string) tempnam(sys_get_temp_dir(), 'flowbeacon-jwks-');

        file_put_contents($this->jwksFile, json_encode([
            'keys' => [[
                'kty' => 'RSA',
                'kid' => self::KEY_ID,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => self::base64UrlEncode($details['rsa']['n']),
                'e' => self::base64UrlEncode($details['rsa']['e']),
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->sdk = new RoboAuthSdk(
            issuer: self::ISSUER,
            audience: self::AUDIENCE,
            jwksUrl: $this->jwksFile,
            cacheTtlSeconds: 60,
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->jwksFile) && is_file($this->jwksFile)) {
            unlink($this->jwksFile);
        }
    }

    public function testVerifyAccessTokenReturnsClaimsForValidToken(): void
    {
        $token = $this->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user_123',
            'org_id' => 'org_123',
            'roles' => ['admin'],
            'scopes' => ['workflows:read'],
            'iat' => time(),
            'exp' => time() + 300,
        ]);

        $claims = $this->sdk->verifyAccessToken($token);

        self::assertSame('user_123', $claims['sub']);
        self::assertSame('org_123', $claims['org_id']);
    }

    public function testVerifyAccessTokenAcceptsAudienceArrays(): void
    {
        $token = $this->sign([
            'iss' => self::ISSUER,
            'aud' => ['accordflow', self::AUDIENCE],
            'sub' => 'user_123',
            'org_id' => 'org_123',
            'roles' => ['admin'],
            'scopes' => ['workflows:read'],
            'iat' => time(),
            'exp' => time() + 300,
        ]);

        $claims = $this->sdk->verifyAccessToken($token);

        self::assertSame(['accordflow', self::AUDIENCE], $claims['aud']);
    }

    public function testVerifyAccessTokenRejectsUnexpectedIssuer(): void
    {
        $token = $this->sign([
            'iss' => 'https://evil.example',
            'aud' => self::AUDIENCE,
            'sub' => 'user_123',
            'org_id' => 'org_123',
            'roles' => ['admin'],
            'scopes' => ['workflows:read'],
            'iat' => time(),
            'exp' => time() + 300,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token issuer.');

        $this->sdk->verifyAccessToken($token);
    }

    public function testVerifyIntentContextReturnsClaimsForExpectedOrg(): void
    {
        $token = $this->sign([
            'intent_id' => 'intent_123',
            'org_id' => 'org_123',
            'issued_at' => time(),
            'expires_at' => time() + 300,
        ]);

        $claims = $this->sdk->verifyIntentContext($token, 'org_123');

        self::assertSame('intent_123', $claims['intent_id']);
    }

    public function testVerifyIntentContextRejectsExpiredContext(): void
    {
        $token = $this->sign([
            'intent_id' => 'intent_123',
            'org_id' => 'org_123',
            'issued_at' => time() - 600,
            'expires_at' => time() - 300,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Intent context expired.');

        $this->sdk->verifyIntentContext($token, 'org_123');
    }

    public function testReturnToValidationRequiresExactOriginMatch(): void
    {
        $allowedOrigins = ['https://app.robo.dev', 'https://flowbeacon.example:8443'];

        self::assertTrue($this->sdk->isReturnToAllowed('https://app.robo.dev/dashboard?next=1', $allowedOrigins));
        self::assertTrue($this->sdk->isReturnToAllowed('https://flowbeacon.example:8443/callback', $allowedOrigins));
        self::assertFalse($this->sdk->isReturnToAllowed('http://app.robo.dev/dashboard', $allowedOrigins));
        self::assertFalse($this->sdk->isReturnToAllowed('https://evil.example/dashboard', $allowedOrigins));
        self::assertFalse($this->sdk->isReturnToAllowed('/relative/path', $allowedOrigins));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sign(array $payload): string
    {
        return JWT::encode($payload, $this->privateKey, 'RS256', self::KEY_ID);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
