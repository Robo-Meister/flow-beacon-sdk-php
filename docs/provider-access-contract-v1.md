# Provider access envelope V1

`rc.provider-access.v1` carries transient provider access from Robo Connector to one governed FlowBeacon execution.

It is intentionally separate from Execution Intent V2:

- **Execution Intent V2** authenticates caller execution identity and correlation.
- **Provider Access V1** supplies the provider credential and provenance selected by Robo Connector.
- Neither contract is a business-completion claim.

## Wire shape

```json
{
  "version": "rc.provider-access.v1",
  "provider": "openai",
  "source": "OWN",
  "consumer_org_id": "org-1",
  "user_config_id": "config-1",
  "share_id": null,
  "usage_receipt_id": null,
  "credential": {
    "type": "api_key",
    "value": "transient-provider-key"
  }
}
```

For `TECHNICAL_SHARE`, both `share_id` and `usage_receipt_id` are required.

## Invariants

1. Supported sources are `OWN` and `TECHNICAL_SHARE`; `NONE` is never transported.
2. V1 supports `credential.type = api_key`.
3. `consumer_org_id` must match the verified execution intent organisation.
4. The envelope is transient and execution-scoped. It must not create or update a persistent FlowBeacon profile.
5. `safeDiagnostics()` excludes the credential value.
6. Technical-share usage receipt identity is evidence that Robo Connector reserved shared capacity; FlowBeacon does not reinterpret or meter that allowance.

## Responsibility split

| Concern | Robo Connector | SDK | FlowBeacon |
| --- | --- | --- | --- |
| provider access resolution | owns | types only | does not infer |
| Technical Share allowance | owns/reserves | preserves receipt id | does not consume RC allowance |
| credential selection | owns | validates wire shape | uses transiently |
| execution identity | signs V2 intent | validates/types | verifies before provider access |
| provider dispatch | requests | no routing | executes |
| persistent provider profile | optional legacy path | no ownership | MUST NOT be created by this contract |

The credential-security hardening phase may replace the transient credential value with an opaque execution reference in a future contract version. V1 deliberately does not prescribe that migration.
