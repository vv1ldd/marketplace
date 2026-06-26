# ADR 0035: Sovereign Secret and Host Encryption Posture

**Scope:** How secrets and host storage are protected across Sovereign contours after
[ADR 0032](0032-sovereign-identity-infrastructure-split.md) and
[ADR 0034](0034-regional-supply-contour-boundary.md).

## Status

Accepted — **2026-06**

## Context

Sovereign infrastructure spans multiple hosts, deployment planes, and identity
contours. Security properties must be expressed in terms of threat models and
trust boundaries, not in terms of a single vendor or deployment tool.

### Layer model

Security is evaluated across independent layers. Compromise at one layer does not
imply protection from another.

| Layer | Examples | What it protects |
|-------|----------|------------------|
| **0 — Physical media** | SSD, persistent disk, detached volume | Offline theft, forensic imaging, mistaken export |
| **1 — Host OS** | kernel, root account, filesystem | Running-system compromise |
| **2 — Runtime** | container engine, orchestration metadata | Process isolation (not encryption) |
| **3 — Application** | services, configuration, business logic | Application-level authorization |
| **4 — Identity / credentials** | bootstrap identity, tokens, certificates | Who may obtain what, and for how long |

Docker and other container runtimes operate at Layer 2. They are **not** security
boundaries against host compromise. If root is obtained on the host, container
isolation, encrypted images, and environment variables do not restore confidentiality.

### Two independent threat models

**Offline media compromise** and **runtime secret exposure** must be treated
separately.

- Host-controlled encryption protects powered-off media: stolen disks, snapshots,
  detached volumes, and forensic copies. It does not protect a live compromised
  host.
- Managed secret storage protects deployment artifacts: Git, compose files,
  deployment databases, backups, and configuration exports. It does not prevent
  a secret from existing in process memory after a service has started.

Long-lived secrets stored in deployment metadata (`.env`, compose, deployment
plane databases) remain a primary operational risk because they are widely
copied, backed up, and inspectable through ordinary operator tooling.

### Architectural principle

**Secrets are infrastructure concerns, not application configuration.**

From this principle:

- An application declares **which** secrets it requires, not **where** they are
  stored.
- Infrastructure decides how secrets are sourced, rotated, and delivered.
- Applications must not depend on a specific secret backend.
- Changing the secret provider must not require business-logic changes.

### Typed secret contract

Applications SHOULD declare required secrets through typed contracts rather than
through transport-specific environment variable names.

A secret contract describes what the secret is and how it must be handled. The
secret provider answers only where the value is retrieved from.

Example:

```yaml
id: protocol/app-signing-key
version:
  preferred: v2
  accept:
    - v1
    - v2
type: signing-key
consumer: simple-l1
required: true
owner: Platform
impact: S3
rotation: rare
lifecycle:
  create: infrastructure
  rotate: infrastructure
  revoke: infrastructure
  destroy: infrastructure
delivery:
  preferred: file
  env_allowed: false
access: persistent-runtime
```

The contract is stable across environments. The resolver can map it to a local
development file, CI/CD injection, managed secret provider, bootstrap-only
retrieval, or future workload identity without changing business logic.

```text
Application
    ↓
Typed Secret Contract
    ↓
Secret Resolver
    ├── env (development compatibility)
    ├── file mount
    ├── managed secret provider
    ├── bootstrap identity
    └── workload identity (target posture)
```

Environment variables may remain a compatibility transport, but they are not the
architectural interface.

Delivery and access are separate properties. Delivery describes how material is
made available (`env`, `file`, `runtime-api`, `managed-secret-provider`). Access
describes when the consumer needs it (`startup`, `persistent-runtime`,
`on-demand`, `periodic-refresh`). A certificate may be delivered as a file and
used continuously; an OAuth token may be resolved on demand and never stored.

Typed contracts enable:

- startup validation that all required secrets are present;
- generated inventory and documentation;
- policy checks, such as blocking `signing-key` delivery through environment
  variables;
- audit of which consumers depend on which secrets.

The Secret Resolver is an infrastructure adapter. It resolves values, validates
delivery policy, and hands material to the consumer. It does **not** decide which
consumer is authorized to use a secret, weaken contract policy, or mutate
provider access controls. Authorization remains with the contract, bootstrap
identity, and infrastructure policy.

### Impact classes

Secrets are prioritized by impact, not by their historical variable names.

| Class | Meaning |
|-------|---------|
| **S0** | Not secret-bearing; may remain deployment metadata |
| **S1** | Compromise affects one service or database account |
| **S2** | Compromise affects an external provider, upstream, or integration boundary |
| **S3** | Compromise enables signing, token issuance, financial operations, or privileged control-plane access |

## Decision

### 1. Applications declare typed secret contracts

Applications SHOULD declare secret dependencies as typed contracts. A contract
SHOULD include at least:

- stable `id`;
- `type`;
- `consumer`;
- `required`;
- `owner`;
- `impact`;
- `rotation`;
- lifecycle ownership;
- versioning rules;
- access pattern;
- delivery policy.

A Secret Resolver maps the contract to the current transport and provider. The
resolver MUST preserve contract policy, including restrictions on environment
variable delivery for high-impact secrets.
The resolver MUST NOT grant access beyond the contract and infrastructure
policy.

### 2. Containers are not encrypted security boundaries

Container images, layers, and runtime metadata SHALL NOT be treated as a
substitute for host storage encryption or managed secret delivery.

### 3. Host storage protects offline compromise

Each Sovereign host SHOULD provide **customer-controlled encryption against
offline media compromise**.

The mechanism is an implementation choice (for example LUKS, cloud KMS with
customer-managed keys, or equivalent). The architectural requirement is that
offline copies of persistent media are unusable without customer-held key
material.

### 4. Long-lived secrets leave deployment metadata

Long-lived secrets SHALL reside in a **Managed Secret Provider** rather than in
deployment metadata.

A Managed Secret Provider is any backend that offers:

- access control bound to workload or host identity;
- audit of secret access;
- rotation support;
- separation of secret values from deployment artifacts.

Concrete implementations (cloud secret managers, Vault, encrypted secret files
with operational key custody) are infrastructure choices and are not fixed by
this ADR.

### 5. Bootstrap identity gates secret retrieval

Runtime SHALL obtain secrets through a **bootstrap identity** with least
privilege.

Bootstrap identity is the narrow, long-lived credential that allows a host or
workload to authenticate to the Managed Secret Provider. It is not a substitute
for application secrets. It exists only to start the trust chain.

### 6. Prefer short-lived credentials

The architecture SHOULD progressively replace permanent credentials with
short-lived credentials wherever supported.

Short-lived credentials reduce blast radius after runtime compromise and enable
automatic rotation. They are a target posture, not a day-one requirement for
every integration.

### Trust chain

```text
Host (Layer 0–1)
    ↓
Bootstrap Identity (Layer 4)
    ↓
Managed Secret Provider
    ↓
Secret Resolver
    ↓
Runtime Secrets (policy-aware delivery)
    ↓
Short-lived Credentials (target posture)
```

Bootstrap identity is the architectural centerpiece: it defines who may bootstrap
a contour, not merely where secrets are stored.

### Secret classes

| Class | Examples | Delivery |
|-------|----------|----------|
| **Public config** | public URLs, feature flags, non-sensitive IDs | deployment metadata is acceptable |
| **Long-lived infrastructure secrets** | database passwords, signing keys, provider API credentials | Managed Secret Provider only |
| **High-sensitivity runtime secrets** | financial signing material, root encryption keys | prefer file mounts or runtime fetch; avoid broad env export where practical |
| **Ephemeral credentials** | OAuth access tokens, short-lived JWTs, workload certificates | issued at runtime; not stored in deployment metadata |

### Non-decisions

- This ADR does not mandate a specific Managed Secret Provider.
- This ADR does not mandate a specific host encryption implementation.
- This ADR does not require immediate removal of all environment variables from
  running containers. It requires that **authoritative storage** of long-lived
  secrets moves out of deployment metadata.
- Secret Manager (or equivalent) does not protect a running process from a
  host-level attacker. That limitation is accepted and documented.

## Consequences

### Benefits

- Removes plaintext long-lived secrets from deployment artifacts.
- Reduces blast radius of backups, snapshots, and repository leaks.
- Enables centralized rotation, auditing, and least-privilege access.
- Aligns Sovereign contours with zero-trust service identity over time.
- Preserves application portability across secret backends.

### Trade-offs

- Bootstrap and secret retrieval add operational complexity.
- The Managed Secret Provider becomes infrastructure dependency.
- Service startup depends on secret availability and identity health.
- Rotation becomes a mandatory operational process, not an optional cleanup.
- Short-lived credential adoption requires incremental service changes.

## Migration plan

Migration is phased. Phases are ordered by risk reduction, not by technology
fashion.

### Phase 1 — Classify and inventory

- Enumerate secrets per contour and per service.
- Label each secret by class (public config, long-lived, high-sensitivity,
  ephemeral target).
- Identify secrets currently present in deployment metadata, compose, and
  deployment-plane databases.

### Phase 2 — Establish Managed Secret Provider

- Select a Managed Secret Provider per contour (may differ by host).
- Create secret namespaces aligned with contour boundaries (ADR 0032).
- Define naming, rotation, and audit conventions.

### Phase 3 — Bootstrap identity per host

- Issue one minimal bootstrap identity per Sovereign host or workload class.
- Scope access to only the secrets required for that contour.
- Document bootstrap recovery and revocation procedures.

### Phase 4 — Secret injection at deploy/runtime

- Replace plaintext long-lived values in deployment metadata with references.
- Retrieve secrets at container start through bootstrap identity.
- Prefer file-based delivery for high-sensitivity material where supported.

### Phase 5 — Short-lived credentials

- Replace permanent inter-service and provider credentials where APIs support
  ephemeral tokens, workload identity, mTLS, or OAuth-style issuance.
- Measure remaining long-lived secrets; repeat classification.

Concrete contour-specific inventory and rollout steps live in
[sovereign-secret-migration-checklist.md](../sovereign-secret-migration-checklist.md).

## Related documents

- [ADR 0032: Sovereign Identity Infrastructure Split](0032-sovereign-identity-infrastructure-split.md)
- [ADR 0034: Regional Supply Contour Boundary](0034-regional-supply-contour-boundary.md)
- [Marketplace Continuity Data Classification](../marketplace-continuity-data-classification.md)
- [Sovereign Secret Migration Checklist](../sovereign-secret-migration-checklist.md)
