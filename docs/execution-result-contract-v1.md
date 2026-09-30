# Execution result contract v1

Task: FLOWBEACON-EXECUTION-RESULT-CONTRACT-CONVERGENCE-001.

Request negotiation: add `result_contract: flowbeacon.execution-result.v1` to the existing governed `POST /call`. Execution Intent V2, bearer authentication and ExecutorResultCorrelation field names remain unchanged. A present invalid contract fails closed. The SDK never extracts JSON from LLM text and never applies domain changes.

Wire envelope:

```json
{
  "schema": "flowbeacon.execution-result.v1",
  "correlation": {
    "caller_invocation_id": "invocation-1",
    "caller_intent_id": "intent-1",
    "correlation_id": "correlation-1",
    "flowbeacon_execution_id": "remote-1",
    "status": "succeeded"
  },
  "output": {
    "schema": "platform.execution_brief_assistance.v1",
    "data": {
      "schema": "platform.execution_brief_assistance.v1",
      "summary": "Review proposal",
      "suggestions": [],
      "blockers": [],
      "uncertainties": []
    }
  },
  "artifacts": [],
  "error": null
}
```

The existing status vocabulary is retained: `succeeded/completed` means validated technical output; `pending/reserved/queued/started/running` means pending; `failed/cancelled` means no applicable output. All responses require distinct caller and remote identities. Failed/cancelled results require `{code, category, retryable}`. Pending results have null output/error and may be returned with HTTP 202. No business acceptance or task completion is implied. Repeat the same authorized request to retrieve a completed result; do not mint another invocation merely to poll. No automatic polling, worker, or cancellation API is added.

`output.data` preserves the existing domain schema discriminator. SDK checks that both schema identifiers agree. Domain validation remains in RC. Version 1 supports the three existing structured-output actions and an empty `artifacts` list; downloading/generated file results require a separately versioned artifact contract. Unknown schema versions, contradictory states and nonempty artifacts fail closed.

```php
$result = Robo\AuthSdk\ExecutionResult::fromArray($decodedResponse);
if ($result->isPending()) { /* retain invocation and wait */ }
if ($result->isCompleted()) { /* validate $result->outputData in the domain */ }
```

## Compatibility and release

Calls without result negotiation retain the previous response. New clients must require v1 and must not guess a successful output from legacy raw text. RC retains one bounded reader for already-persisted legacy business_result envelopes. Invalid negotiated envelopes do not downgrade.

The SDK source is mirrored in ai-module/shared-profile/sdk/robo-auth-sdk/php. Publish both identical additions; never retag v0.0.2 or v0.0.3. The RC draft pins the companion SDK branch to an immutable commit until a reviewed SDK release exists. Replace that development pin and regenerate the lock through Composer before production promotion.

## Verification

`php tests/execution-result-contract.php` runs without Composer dependencies. Three JSON fixtures were generated from the actual Python `/call` route with isolated provider/store collaborators. They are shared byte-for-byte with the FlowBeacon and RC PRs. PHP execution was unavailable in the preparation environment (`php: command not found`); this is authored coverage, not a passing PHP claim.
