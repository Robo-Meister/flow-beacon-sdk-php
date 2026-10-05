# Execution boundary v1 — receiver-first stage 2

Task: `AI-INTEGRATION-EXECUTION-BOUNDARY-PROJECTION-002`.
This PR supplies the wire contract. It does **not** complete the RC policy producer or enable boundary-governed traffic.

## Ownership and negotiation

RC evaluates canonical permissions, resource access, Risk/Regulation/gates, and provider disclosure policy before issuing a grant. The SDK validates a projection; FlowBeacon enforces its technical restrictions. Neither descriptive brief text, a model instruction, an authenticated actor's read access, nor provider credential ownership is a grant to disclose content.

The signed Execution Intent V2 JWT may contain `execution_boundary`. The caller must also select `result_contract=flowbeacon.execution-result.v1+execution-boundary.v1` and send typed `execution_input`. The returned envelope remains `flowbeacon.execution-result.v1`, with an additional `boundary` receipt. A stage-1 receiver rejects the new result-contract selector before dispatch. Never fall back to the old selector after rejection. Unsigned body boundaries, a missing signed grant, or a signed grant with the old selector fail closed.

After authenticating the result's normal correlation, a producer must require `ExecutionResult::$boundary` and call the original grant's `assertReceipt()`. A receipt acknowledges which projection the receiver processed; it does not mean permission was granted, the provider succeeded, review occurred, or business work completed. Inspect result status/error as well.

## Closed proposal-only contract

See `tests/Fixtures/execution-boundary-v1.json` for synthetic, non-production data.

| Field | Meaning and constraint |
| --- | --- |
| `schema` | `execution-boundary.v1` |
| `id`, `policy_version` | Immutable opaque identifiers, max 255 UTF-8 bytes |
| `decision_refs` | 1–32 unique canonical decision references, also present in signed intent `decision_refs` |
| `evaluated_at`, `valid_until`, `deadline` | Positive integer epoch seconds; evaluated < deadline <= validity <= evaluated + 300 |
| `operations` | Exactly `["generate_proposal"]` |
| `resources` | Exactly the signed intent's source `{type,ref,version,digest}`; an input snapshot reference, **no permission to read another resource** |
| `effects` | Exactly `[]`: no business mutation, tool execution, delivery, approval or external action |
| `review_policy` | Exactly `human_review.required`; canonical RC review/apply paths remain mandatory |
| `data_egress` | Unique provider allowlist, exact typed-input SHA-256, `store=false` |
| `limits` | Exactly one provider call, max output tokens 1–32768, serialized input bytes 1–524288 |
| `expected_output_schema` | Must match the server's exact registered task/version output profile |

Unknown keys, types, operations or expanded effects are rejected. The source/egress input binding is exact, including roles and message order. Input JSON is recursively sorted by object key, compact, UTF-8, unescaped Unicode/slashes/line terminators, preserving array order. `ExecutionBoundary::canonicalJson()` and FlowBeacon `canonical_json()` implement this representation. Only schema-validated structures containing integers/strings/booleans/arrays are hashed. The receipt digest hashes the entire boundary by the same encoding.

Use `ExecutionBoundary::fromArray()`, then `assertMatches($claims, $input)` before signing. `ExecutionIntentContext::fromClaims()` checks the boundary's source and decision references, but does not have the request input and cannot replace `assertMatches()`. Constructing a DTO is never authorization to issue it.

## Freshness, replay and limits

The entire immutable boundary participates in the existing request digest. Changed policy, limits, source or validity under the same idempotency key conflicts. Fresh authentication may observe an existing pending/terminal execution with its original expired grant, without another provider call. A new execution with an expired/future-dated grant receives `BOUNDARY_REEVALUATION_REQUIRED`. Retrying that same recorded failure only observes it; it never renews permission.

RC must recompute current canonical decisions before new dispatch. A changed or expired decision must return to that assessment and the domain's explicit new-attempt decision; do not just extend the timestamps or generate another idempotency key. Version strings alone do not implement live revocation. This receiver-first change provides no revocation callback, worker recovery, lease, or cancellation protocol.

The executor checks policy validity before quota and again directly before dispatch. It enforces one outbound attempt, the provider allowlist, input bytes, output-token ceiling and output schema. The adapter bounds connect/read timeouts by remaining deadline, checks streaming chunks, and rejects late results as `PROVIDER_OUTCOME_UNKNOWN`. It cannot prove remote cancellation or impose a hard wall-clock process kill. Token ceilings are not monetary budgets or exact input-token accounting; accounting/reconciliation remains stage 4. No tool loop or resource reader is introduced.

## Required producer follow-up

Do not synthesize `allowed=true` from actor access or from descriptive Risk/Regulation facts. The RC producer must project explicit canonical decisions for permissions, egress, risk/gates, resource scope and review; unresolved critical dimensions must deny the affected operation or enter canonical review. It must retain the exact grant across retries, preserve current reauthorization at entry, require the receipt and use the new selector. All four profiles require producer-side acceptance before enabling this protocol.

Existing callers/results lacking the optional boundary remain unchanged. This is an opt-in receiver capability, not a global requirement or a production enablement claim.

## Verification

`php tests/execution-boundary-contract.php` covers roundtrip, signed-claims binding, input/receipt digest parity, rejection and legacy result compatibility. PHP is unavailable in the preparation environment; this command is NOT RUN. PHP syntax was checked with tree-sitter, which is not runtime evidence. The shared fixture is independently validated by the Python receiver tests. No dependency changes or database migration.
