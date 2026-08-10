# FlowBeacon API SDK (PHP)

Composer package: [`robo-meister/flow-beacon-api`](https://packagist.org/packages/robo-meister/flow-beacon-api)

This package provides the PHP helpers needed by FlowBeacon integrations that rely on Robo account access tokens and signed intent context tokens.

## Trust boundaries

### Account access token

An account access token authenticates the Robo account and organisation. Call
`verifyAccessToken()` to retain the established account-token behavior.

### Execution intent

An execution intent authenticates and correlates one Robo-authorized execution
request. V1 minimal intent contexts remain supported by `verifyIntentContext()`.
The additive V2 contract is verified by `verifyExecutionIntent()` and returns an
immutable `ExecutionIntentContext`, rather than an unvalidated claim array.

**Authentication != business eligibility. Valid execution intent != canonical outcome.**

A valid signature proves only that Robo Connector issued the exact scoped
execution request with the represented identity and correlation context. It does
not prove FlowBeacon permission, unrelated user permissions, Risk acceptance,
Quality approval, canonical AI output, or completion of business work.

## Requirements

- PHP 8.2 or newer
- Composer 2.x
- `firebase/php-jwt` 7.x

## Install

```bash
composer require robo-meister/flow-beacon-api
```

## Usage

```php
use Robo\AuthSdk\RoboAuthSdk;

$sdk = new RoboAuthSdk(
    issuer: 'https://account.robo.dev',
    audience: 'flowbeacon',
    jwksUrl: 'https://account.robo.dev/.well-known/jwks.json'
);

$claims = $sdk->verifyAccessToken($jwt);
$intent = $sdk->verifyIntentContext($intentJwt, $claims['org_id']);
$executionIntent = $sdk->verifyExecutionIntent($executionIntentJwt, $claims['org_id']);
```

For example, an inventory application can sign distinct
`inventory.restock.propose@2026-08-01` business and
`restock_forecast_v2@2026-08-01.3` technical identities. The structured WorkRef
can identify `inventory_item` / `item-42` without exposing or resolving the item.
An absent workspace remains absent; it is never inferred from the organisation.

The caller's `idempotency_key` is authenticated and preserved, but the SDK does
not guarantee remote exactly-once execution. Replay suppression, orchestration,
provider selection, retries, and fallback are FlowBeacon runtime concerns.

See [the V2 execution-intent contract](docs/execution-intent-contract-v2.md) for
the claim schema, result correlation, and responsibility boundaries.

## Return-to validation

`isReturnToAllowed()` compares the full origin (`scheme://host[:port]`) of the requested return URL against the allow-list. Paths and query strings are ignored after the origin match succeeds.

```php
$allowed = $sdk->isReturnToAllowed($returnTo, [
    'https://app.robo.dev',
    'https://flowbeacon.example:8443',
]);
```

## Development

Install dependencies and run the package checks from this directory:

```bash
composer install
composer validate --strict
composer test
```

## Packagist release checklist

1. Ensure `composer validate --strict` passes.
2. Tag a semantic version, for example `v0.1.0`.
3. Submit the repository or subtree split URL to Packagist with package name `robo-meister/flow-beacon-api`.
4. Confirm Packagist reads this directory's `composer.json` and that the package page lists the expected autoload namespace.
