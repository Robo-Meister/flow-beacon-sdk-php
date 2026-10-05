# Changelog

All notable changes to `robo-meister/flow-beacon-api` will be documented in this file.

## Unreleased

- Normalize malformed execution-result correlation types to InvalidArgumentException, preserving the TypeError cause; cover optional remote/provider/model IDs and required correlation fields.

- Add negotiated execution-result v1 DTO, pending/error semantics and three cross-repository wire fixtures.

- Add immutable signed execution-intent V2 verification and typed executor-result correlation.
- Preserve all V1 intent, account-token, and return-origin APIs.

## 0.1.0 - Unreleased

- Prepared the PHP SDK package metadata for Composer/Packagist publishing.
- Added PHPUnit coverage for JWT verification, intent contexts, and return-to origin checks.


## Unreleased — governed execution input

- Add role-preserving ExecutionInput and safe ExecutionEvidence contracts.
- Preserve optional negotiated execution provenance in ExecutionResult without changing historical envelopes.
- Add four-profile fixtures and dependency-free contract checks.
