# FlowBeacon SDK parity 002

The published PHP SDK is the canonical Composer distribution. The embedded PHP SDK under ai-module/shared-profile/sdk/robo-auth-sdk/php must mirror its ExecutionResult, ExecutorResultCorrelation and execution-result-contract.php. This change preserves Execution Intent V2 and execution-result v1; it does not grant business completion authority.

## Discovery and change

SDK baseline ee191181bf7d4744f1669c35d16a382b35570daa; ai-module baseline ad828591fda35c8c3ad39ce70f6ef049296a40ff. Neither tree contains AGENTS.md. ExecutorResultCorrelation and all three wire fixtures matched. The embedded ExecutionResult already normalized TypeError, but the standalone SDK and its tests lagged behind. The standalone SDK now uses exactly that normalization. Both tests cover array/object/integer/float/boolean values in all three optional identity fields, preserve the TypeError cause, reject malformed required fields and allow absent/null provider/model IDs.

The three schemas and empty artifacts contract remain unchanged. No migration, provider invocation, pack, permission, context, business event or workflow is introduced. DB_NOT_APPLICABLE for this pure decoder/test change. Applicable direction: task FLOWBEACON-SDK-PARITY-AND-CONSUMER-PIN-002 and existing advisory/human-review boundary.

## Verification

PHP 8.3.6: php tests/execution-result-contract.php PASS in the standalone SDK and at the embedded SDK path. Three fixture files compared byte-for-byte across standalone SDK, embedded SDK and RC: PASS. PHP lint and git diff --check: PASS. The expanded standalone contract test fails on the pre-fix ExecutionResult with uncaught TypeError and passes with the fix.

RC transport performs an earlier ExecutorResultCorrelation parse, so its companion PR also catches TypeError narrowly at that correlation verification boundary. A SDK-only merge does not complete the consumer update. No deployment, PostgreSQL replay, full server runtime E2E or production-readiness claim.

## Maintenance and remaining debt

For each contract change compare both src/ExecutionResult.php, src/ExecutorResultCorrelation.php, tests/execution-result-contract.php and the three tests/Fixtures/ExecutionResult JSON files; run each SDK contract command and RC tests/AI/Contract/execution-result-consumers.php against the exact pinned SDK. Do not accept a fix confined to the embedded copy. The integration maintainer owns mirror drift risk until a separately approved distribution change removes the copy. The RC pin must follow the final reviewed SDK commit; replacing it with an immutable release requires Composer, never retagging an existing release.
