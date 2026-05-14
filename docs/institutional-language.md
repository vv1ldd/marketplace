# Institutional Language Glossary

This document formally establishes the unified mental model and sovereign engineering vocabulary for our business operating system. All engineering teams, product owners, and operators must adhere to this terminology to preserve the formal integrity of the institutional stack.

---

## 🏛️ Structural Taxonomy & Authority Matrix

| Legacy Term | Unified Institution | Sovereign Domain | Authority Type | Aesthetic Vibe |
| :--- | :--- | :--- | :--- | :--- |
| `Admin Panel` | **Operations Command** | `/ops` | Operational Authority | Active, Commercial (Amber) |
| `Finance Section` | **Treasury Nexus** | `/treasury` | Financial Authority | Institutional, Reserve (Emerald/Gold) |
| `Audit/Ledger` | **Integrity Tribunal** | `/tribunal` | Truth Authority | Judicial, Cryptographic (Indigo) |
| `Infrastructure` | **System Kernel** | `/kernel` | Infrastructure Authority | Military, Low-Level Steel (Slate) |
| `Partner Portal` | **Consortium Terminal** | `/partner` | External Authority | Interconnected Network (Indigo) |

---

## 🔤 Technical & Operational Glossary

| Old Generic Term | Institutional Standard | Architectural Intent / Context |
| :--- | :--- | :--- |
| **Logs** | `Ledger Events` | Reinforces cryptographic immutability; not just text, but state commitments. |
| **Balances** | `Treasury Reserves` | Defines internal liquidity holdings rather than simple database counters. |
| **Providers** | `Liquidity Gateways` | Defines raw external integrations as institutional rails. |
| **Healthcheck** | `Vital Signs` | Represents the living biology and low-level machine capability of the system. |
| **Payments** | `Settlement Rails` | References the formal clearing of financial transfers. |
| **FX Setup** | `FX Oracle` | Highlights that exchange rates are trusted external feeds broadcasted to the machine. |

---

## 📖 Architectural Lore
The stack does not "render dashboards"—it orchestrates a Sovereign State-Model. 
- **Operations Command (`/ops`)** dictates commercial expansion.
- **Treasury Nexus (`/treasury`)** dictates the laws of money movement and liquidity geography.
- **System Kernel (`/kernel`)** guarantees raw machine survival.
- **Integrity Tribunal (`/tribunal`)** is the absolute, final court of truth.

---

## ⚖️ Epistemic Ontology & Authority Rules

To prevent conflict of interest and maintain formal integrity, the system strictly decouples execution from verification:

1. **The Chain of Truth:** `Event ➡️ Ledger ➡️ Tribunal ➡️ Canonical Truth`.
2. **Execution Authority (Treasury):** Mutates state, allocates reserves, schedules settlements.
3. **Truth Authority (Tribunal):** An independent observer. It must **never** have write-access to Treasury. It reads event streams and reconstructs state to confirm reality externally.

---

## 📊 Institutional Metrics Framework

Telemetry should elevate beyond raw hardware counters and speak the language of Sovereign Infrastructure:

| Legacy Metirc | Sovereign Standard | Context |
| :--- | :--- | :--- |
| `CPU/RAM %` | **Consensus Health** | Machine resource availability to process immutable commitments. |
| `Jobs Count` | **Liquidity Pressure** | The throughput load on pending rails and settlement events. |
| `DB Active %` | **Treasury Stability** | The physical integrity of ledger read/write channels. |
| `Sync Delay` | **Tribunal Drift** | The divergence between physical execution and final audited consensus. |
| `Failed Jobs` | **Settlement Exposure** | Financial volume currently caught in un-reconciled state failure. |

