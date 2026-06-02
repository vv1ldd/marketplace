# Marketplace Continuity Data Classification

## Purpose

This document classifies marketplace data by continuity requirements. It supports:

- `RTO < 60 seconds` for operational recovery;
- `RPO = 0` for accepted transitions;
- ciphertext-only operational storage;
- replayable operational projections.

## Class A: Authority Critical

Class A data preserves legitimate history. It must survive primary database loss, blocking, migration failure, and failover.

Requirement: `RPO = 0`.

Current candidates:

- `sovereign_ledger`
- `wallet_ledger_entries`
- accepted order transitions
- accepted balance transitions
- settlement commits
- authority decisions
- Simple L1 anchor references
- idempotency keys for Class A commands
- writer authority epochs and fencing state

Important current code paths:

- `app/Services/LedgerService.php`
- `app/Services/L1StateService.php`
- `app/Services/BuyerWalletService.php`
- `app/Services/MeanlyRetailCheckoutService.php`
- `app/Jobs/ProcessRedeemWildflowPurchase.php`

Class A data must be append-only or guarded by explicit authority decisions. Mutable SQL columns may exist as projections, but not as the only record of truth.

## Class B: Rebuildable Projections

Class B data is operationally useful but rebuildable from Class A history and authority.

Requirement: may lag, be discarded, or be rebuilt.

Current candidates:

- order read models
- balance columns on partner/legal entity rows
- `wallet_accounts`
- catalog search indexes
- canonical product identity indexes
- analytics aggregates
- demand gap metrics
- continuity dashboard summaries
- provider catalog projections

Architectural test:

> Can this table be dropped and rebuilt?

If not, it must be reclassified as Class A.

## Class C: Ephemeral

Class C data may be lost during failover.

Requirement: no continuity guarantee.

Current candidates:

- sessions
- caches
- temporary observations
- queue scratch state
- transient search journey state
- temporary provider sync state

Class C data must not be required to prove accepted transitions, authority decisions, or replay correctness.

## Ciphertext-Only Audit

Existing encrypted patterns include:

- `App\Casts\VaultEncrypted`
- `App\Casts\VaultEncryptedJson`
- blind indexes for equality lookup such as `*_bidx`

Known encrypted areas:

- legal entity PII and bank details;
- user, customer, and seller PII;
- order `info` and `client_info`;
- order item codes and client info;
- provider credentials and settings;
- product/provider catalog blobs;
- shop credentials and notification secrets.

Areas requiring hardening review:

- `SovereignLedger.payload`
- `SovereignLedger.input_data`
- `SovereignLedger.output_state`
- `WalletLedgerEntry.payload`
- `WildflowKernelOrder.request_payload`
- `WildflowKernelOrder.response_payload`
- `ZeroLayerIntegration.credentials`
- `ZeroLayerIntegration.settings`
- plain API tokens or compatibility tokens on partner/legal entity models

Rule:

> Operational storage and Simple L1 anchors must not require plaintext marketplace-sensitive data.

## Transition To Replay Authority

The current ledger already provides hash-chained audit behavior. The continuity target requires a staged transition:

1. Treat ledger entries as Class A accepted transitions.
2. Treat mutable balance/order columns as projections where possible.
3. Add outbox durability for Class A writes.
4. Add replay commands for balances, orders, settlements, and provider fulfillment state.
5. Add verification commands for each projection.
6. Register projections in the Projection Rebuild Registry.
