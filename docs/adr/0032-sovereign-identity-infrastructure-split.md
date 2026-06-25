# ADR 0032: Sovereign Identity Infrastructure Split

**Scope:** Documents the infrastructure boundary for commerce identity contours
after [ADR 0030](0030-storefront-login-uses-issuer-par-and-ceremony-origin.md).

## Status

Accepted - **2026-06**

## Decision

**Contour = infrastructure boundary**, not only DNS branding.

| Contour | Hosts | Server |
|---------|-------|--------|
| **ONE** | `meanly.one`, `api.meanly.one`, `pass.meanly.one`, `connect.identity.meanly.one`, `identity.meanly.one` | GCP `lena-1-gcl` (`34.39.244.55`) |
| **RU** | `meanly.ru`, `api.meanly.ru`, `pass.meanly.ru`, `connect.identity.meanly.ru`, `identity.meanly.ru` | lena (`135.106.162.147`) |
| **Protocol** | `pass.simplelayer.one`, `simplelayer.one` | lena (root) |

Each commerce contour runs a **separate** `simple-l1` instance with its own
sovereign ledger volume, `SL1_PASS_ISSUER_HOST`, `SL1_ISSUER_CEREMONY_MAP`, and
`SL1E_CLIENT_REGISTRY_JSON`.

## rpId

Per [ADR 0028](0028-canonical-webauthn-rp-boundary.md):

- `connect.identity.meanly.one` -> `identity.meanly.one`
- `connect.identity.meanly.ru` -> `identity.meanly.ru`

WebAuthn credentials do **not** cross contours. Re-enrollment after split is
expected, not a migration error.

## PAR chain (both contours)

```text
storefront
  -> api (PAR push)
  -> pass.<contour>/r/sl1rq_...
  -> connect.identity.<contour> ceremony
  -> callback
```

## Non-decision

`ops.meanly.one` / `ops-gcl.meanly.one` Coolify clients remain registered on the
GCP `.one` issuer. `pass.simplelayer.one` protocol namespace stays on lena.
