# Governed execution input v1

`FLOWBEACON-OPENAI-GOVERNED-REQUEST-CONVERGENCE-001`, stage 1.

`POST /call` negotiates `flowbeacon.execution-input.v1` through the `execution_input`
body field, beside the existing signed Execution Intent V2, caller bearer,
`provider_access` and `result_contract=flowbeacon.execution-result.v1`.
The structured request MUST NOT contain `prompt`. An older receiver then rejects
before provider dispatch instead of silently flattening or downgrading the request.

```json
{
  "schema": "flowbeacon.execution-input.v1",
  "messages": [
    {"role": "system", "content": "Application-owned instructions; propose for review."},
    {"role": "user", "content": "Authorized source data, encoded by the domain renderer."}
  ]
}
```

The exact keys are required; unknown fields are rejected. This version supports one
instruction (`system` or `developer`) followed by one `user` message. Both are
nonempty UTF-8 strings; combined content is at most 262144 UTF-8 bytes. Content,
role and ordering are preserved, never reconstructed from delimiter text. Tool,
assistant/history, binary and resource inputs require a future contract. Input
contains no model, endpoint, credential, policy verdict or provider selection.
Business authorization remains with Robo Connector; instructions grant no rights.

For accepted structured input, the existing result envelope gains optional
`execution`, parsed by `ExecutionEvidence`. Historical result envelopes remain
byte-shape-compatible: SDK serialization omits this field when it was absent.
A new producer requires acknowledgement and must reject a success without profile
and completed provider-attempt evidence. Pending and preflight failures may have a
null profile/attempt and never contain applicable output.

The evidence has schema `flowbeacon.execution-evidence.v1`, `input_contract`,
`profile` and `attempt`. The profile contains ID/version, exact task key/version,
provider/API, snapshot model, explicit supported capabilities, output token limit,
`store=false`, transport timeout, native output schema and SHA-256 digest. The
digest uses UTF-8 sorted-key compact JSON of the profile without `digest` (no
NaN/Infinity, Unicode unescaped). The complete server-owned profile is committed
in the existing FlowBeacon execution ledger before dispatch. SDK treats the digest
as server provenance, not independent cryptographic authentication.

Attempt evidence contains number (1 in v1), request/response IDs, actual model,
status, and available nonnegative input/output/total token counters. Missing usage
is unknown, not zero. No raw provider error, response text, refusal reason, request
headers or credentials are permitted in evidence. Statuses: received, completed,
refused, incomplete, failed, rejected, outcome_unknown. Identifiers are bounded to
255 characters and restricted to ASCII identifier characters.

Errors distinguish `PROVIDER_REFUSAL`, `PROVIDER_OUTPUT_INCOMPLETE`,
`PROVIDER_AUTHENTICATION_FAILED`, `PROVIDER_RATE_LIMITED`, `PROVIDER_REQUEST_REJECTED`,
`PROVIDER_OUTCOME_UNKNOWN`, `PROVIDER_MODEL_MISMATCH`, `PROVIDER_RESPONSE_INVALID`,
`OUTPUT_SCHEMA_INVALID`, and configuration/input errors. Rate limiting permits a
future invocation; an ambiguous transport outcome does not authorize paid retry.
Re-observing the same identity returns the saved failure or pending execution.
No error or pending result authorizes domain apply.

## Ownership, rollout and verification

This DTO owns transport validation only. Existing WorkRef, Intent V2, tenant and
correlation contracts remain authoritative. No persistence, new domain status,
capability grant or migration is introduced (`DB_NOT_APPLICABLE` for this SDK).
Both SDK copies carry identical classes, fixtures and this document.

Merge SDK and receiver before enabling the RC producer. RC pins the companion SDK
commit and defaults `AI_FLOWBEACON_STRUCTURED_INPUT_ENABLED=0`. Existing invocation
payloads retain their original wire version, including after flag changes.

Checks: `php tests/execution-input-contract.php` and
`php tests/execution-result-contract.php`; then the consumer PHPUnit suite and the
receiver payload/replay tests. PHP runtime was unavailable in the authoring
environment, so these PHP commands are NOT RUN; syntax was checked with a PHP
parser. That check is not a substitute for executing PHP or full application boot.
