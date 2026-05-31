# FlowBeacon API SDK (PHP)

Composer package: [`robo-meister/flow-beacon-api`](https://packagist.org/packages/robo-meister/flow-beacon-api)

This package provides the PHP helpers needed by FlowBeacon integrations that rely on Robo account access tokens and signed intent context tokens.

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
```

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
