<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

final class RoboAuthSdk
{
    /** @var array<string, mixed> */
    private array $cachedKeys = [];
    private ?int $cacheExpiresAt = null;

    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly string $jwksUrl,
        private readonly int $cacheTtlSeconds = 86400
    ) {
    }

    /**
     * Verify an account access token and return its claims.
     *
     * @return array<string, mixed>
     */
    public function verifyAccessToken(string $jwt): array
    {
        $keys = $this->getKeys();
        $payload = (array) JWT::decode($jwt, $keys);

        if (($payload['iss'] ?? null) !== $this->issuer) {
            throw new RuntimeException('Invalid token issuer.');
        }

        $aud = $payload['aud'] ?? null;
        if ($aud !== $this->audience && (!is_array($aud) || !in_array($this->audience, $aud, true))) {
            throw new RuntimeException('Invalid token audience.');
        }

        $required = ['sub', 'org_id', 'roles', 'scopes'];
        foreach ($required as $claim) {
            if (!isset($payload[$claim])) {
                throw new RuntimeException(sprintf('Missing claim %s.', $claim));
            }
        }

        return $payload;
    }

    /**
     * Verify a signed intent context token for the expected organization.
     *
     * @return array<string, mixed>
     */
    public function verifyIntentContext(string $jwt, string $expectedOrgId): array
    {
        $payload = (array) JWT::decode($jwt, $this->getKeys());

        foreach (['intent_id', 'org_id', 'issued_at', 'expires_at'] as $claim) {
            if (!isset($payload[$claim])) {
                throw new RuntimeException(sprintf('Missing claim %s.', $claim));
            }
        }

        if (($payload['org_id'] ?? null) !== $expectedOrgId) {
            throw new RuntimeException('Intent org_id mismatch.');
        }

        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            throw new RuntimeException('Intent context expired.');
        }

        return $payload;
    }

    /**
     * Verify and validate a signed V2 execution intent.
     *
     * Verification authenticates the exact caller request and correlation
     * context only; it does not confer authorization or business completion.
     */
    public function verifyExecutionIntent(string $jwt, string $expectedOrgId): ExecutionIntentContext
    {
        if (trim($expectedOrgId) === '') {
            throw new RuntimeException('Expected org_id must be non-empty.');
        }

        $decoded = JWT::decode($jwt, $this->getKeys());
        $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($claims)) {
            throw new RuntimeException('Execution intent payload must be an object.');
        }
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new RuntimeException('Invalid execution intent issuer.');
        }
        $intent = ExecutionIntentContext::fromClaims($claims);
        if (!hash_equals($expectedOrgId, $intent->organisationId)) {
            throw new RuntimeException('Execution intent org_id mismatch.');
        }
        if ($intent->expiresAt <= time()) {
            throw new RuntimeException('Execution intent expired.');
        }

        return $intent;
    }

    /**
     * Check whether a return URL matches one of the explicitly allowed origins.
     *
     * @param array<int, string> $allowedOrigins
     */
    public function isReturnToAllowed(string $returnTo, array $allowedOrigins): bool
    {
        $returnToOrigin = $this->normalizeOrigin($returnTo);
        if ($returnToOrigin === null) {
            return false;
        }

        foreach ($allowedOrigins as $origin) {
            $allowedOrigin = $this->normalizeOrigin($origin);
            if ($allowedOrigin !== null && hash_equals($allowedOrigin, $returnToOrigin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getKeys(): array
    {
        if ($this->cacheExpiresAt !== null && time() < $this->cacheExpiresAt) {
            return $this->cachedKeys;
        }

        $jwksJson = file_get_contents($this->jwksUrl);
        if ($jwksJson === false || $jwksJson === '') {
            throw new RuntimeException('Unable to fetch JWKS.');
        }

        $payload = json_decode($jwksJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JWKS payload.');
        }

        $this->cachedKeys = JWK::parseKeySet($payload);
        $this->cacheExpiresAt = time() + $this->cacheTtlSeconds;

        return $this->cachedKeys;
    }

    private function normalizeOrigin(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true) || !is_string($host)) {
            return null;
        }

        $origin = strtolower($scheme) . '://' . strtolower($host);
        if (is_int($port) && !$this->isDefaultPort($scheme, $port)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return (strtolower($scheme) === 'https' && $port === 443)
            || (strtolower($scheme) === 'http' && $port === 80);
    }
}
