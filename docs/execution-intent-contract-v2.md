# Signed execution-intent contract V2

```text
Robo access token
          +
signed execution intent V2
          ↓
FlowBeacon verifies caller identity/context
          ↓
FlowBeacon executes internally
          ↓
correlated executor result
```

## Security invariant

A valid signed execution intent proves only:

> Robo Connector issued this exact scoped execution request with this identity and correlation context.

It does **not** prove that FlowBeacon granted permission, an unrelated user
permission, Risk acceptance, Quality approval, canonical AI output, or business
work completion. Authentication is not business eligibility, and a valid intent
is not a canonical outcome. The SDK validates the contract; it neither resolves
WorkRefs nor makes business decisions.

## Signed claims

V2 is a signed JWT whose `contract_version` is exactly `"2"`. It contains:

- `intent_id`, the caller-preallocated `invocation_id`, and `org_id`;
- optional `workspace_id` (omission means not applicable; an empty value is invalid);
- structured `work_ref` with non-empty `type` and `id`;
- separate `business_action` (`key`, `version`), `binding` (`id`, `version`),
  and `technical_task` (`key`, `version`);
- safe `source` metadata: required `version` and SHA-256 hex `digest`, plus
  optional `ref` and `type`;
- `correlation_id`, optional `causation_id`, `execution_root_id`, and
  `parent_execution_id`;
- `idempotency_key`; and
- custom `issued_at` / `expires_at` or standard JWT `iat` / `exp` timing claims.

Opaque non-empty `decision_refs` may be included. They are evidence references,
not authority. Tokens must not contain permission decisions, entitlement or
Risk/Quality reasoning, credentials, hidden prompts, or protected content.

`verifyExecutionIntent()` verifies the JWT signature, expected organisation,
expiry and exact supported contract version, then validates every structure and
returns an immutable typed value. Existing V1 tokens and
`verifyIntentContext()` retain their existing semantics.

## Identity, idempotency, and results

The Robo invocation ID exists before transport. FlowBeacon must not replace it
with its own execution, provider-invocation, model-run, or agent-run identifier.
`ExecutorResultCorrelation` preserves caller invocation and intent IDs,
correlation ID, technical status, and optional separate FlowBeacon execution,
provider, and model identifiers. Technical status does not state business
completion.

The idempotency key preserves caller identity only. Its presence makes no
exactly-once guarantee; replay suppression belongs to a consuming service.
Likewise, model/provider selection, retries, fallback, and agent orchestration
remain FlowBeacon responsibilities.

## Responsibilities

| Concern | Robo Connector | SDK | FlowBeacon |
| --- | --- | --- | --- |
| WorkRef ownership | Owns and preallocates | Validates opaque structure | Preserves; does not resolve Robo state |
| business eligibility | Decides locally | No authority | Does not infer from intent validity |
| intent signing | Issues/signs exact request | Defines interoperable claims | Trusts only after verification |
| intent verification | Supplies expected organisation | Verifies and types | Calls SDK before use |
| provider/model selection | Does not prescribe | Does not route | Selects internally |
| invocation identity | Preallocates caller ID | Preserves it | Never overwrites it |
| remote execution ID | Does not alias caller ID | Keeps IDs distinct | Allocates when applicable |
| idempotency identity | Preallocates key | Authenticates/preserves | May suppress replay |
| AI executor result | Correlates receipt | Types technical correlation | Produces technical result |
| canonical outcome | Decides in its domain | Makes no claim | Makes no business-completion claim |

## Release assessment

The Composer package remains `robo-meister/flow-beacon-api` under the existing
`Robo\AuthSdk` namespace. Repository history contains no package-specific release
tag on the current commit; the change is additive and suitable for the next
backward-compatible minor release after normal publication checks.

SDK_CONTRACT_READY
