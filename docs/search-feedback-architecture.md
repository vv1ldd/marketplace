# Search Feedback Architecture

## Context

Search now has a deterministic model layer:

```text
Catalog Facts
  -> SearchProfileBuilder
  -> SearchProfile
  -> SearchRankingEngine
  -> Suggest/Search API
```

`SearchProfile` is materialized at write time, versioned, rebuildable, and deterministic. Runtime search reads profiles and makes ranking decisions without mutating the profile layer.

The next layer is a feedback loop that helps the system evolve based on real completed interactions without breaking determinism.

## Decision

Introduce a separate Search Feedback Intelligence bounded context:

```text
Runtime Search
  -> emits completed interaction events

Feedback Intelligence
  -> append-only events
  -> aggregates
  -> insights
  -> recommendations

Human / Rule Approval
  -> approved changes
  -> SearchProfile rebuild
```

This is a control loop, not a runtime ranking shortcut.

## Invariants

### Invariant 1

Feedback is append-only.

Raw interaction events record what happened and must not be updated to alter history.

### Invariant 2

Feedback must not mutate SearchProfile directly.

Feedback may recommend changes. SearchProfile changes must go through approved rules or human-reviewed changes and a deterministic rebuild.

### Invariant 3

All SearchProfile changes should be explainable through:

```text
events
  -> insights
  -> approved recommendation
  -> rebuild
```

### Invariant 4

Recommendation is not Change.

A recommendation may remain unapplied forever. Some observations should be monitored, rejected, or deferred rather than written into profile rules.

### Invariant 5

Runtime search behavior must not silently drift from feedback.

Feedback influences future behavior only through explicit, reviewable changes and profile/ranking rebuilds.

## Layer Responsibilities

### Events

Events answer: what happened?

Recommended event contract:

```text
query
surface: suggest|full_search
results_count
top_result_id
top_match_reason
selected_result_id
selected_rank
created_at
```

Events should represent completed interactions, not every keystroke.

Do not record noisy streams such as:

```text
p
pl
pla
play
plays
```

### Insights

Insights answer: what does it mean?

Examples:

```text
ZERO_RESULT_HOTSPOT
LOW_RESULT_HOTSPOT
ALIAS_GAP
COVERAGE_GAP
RANKING_GAP
REGION_MISMATCH
BRAND_MISMATCH
```

Insights are derived from event aggregates and can be recalculated.

### Recommendations

Recommendations answer: what might we change?

Examples:

```text
Add alias: "tr" -> "turkey"
Add product alias: "switch oled" -> Nintendo Switch family
Investigate missing Spotify TR coverage
Boost profile family for query cluster "xbox usa"
```

Recommendations are proposed diffs, not applied changes.

### Approved Changes

Approved changes answer: what do we actually change?

Approved changes may update:

- alias dictionaries
- region mappings
- category mappings
- curation overrides
- ranking rule configuration

Approved changes must be reviewable and compatible with deterministic rebuilds.

## Non-Goals

This feedback loop is not ML training.

This feedback loop does not introduce:

- embeddings
- vector search
- automatic profile mutation
- automatic ranking rule mutation
- silent self-tuning
- request-time SearchProfile changes

The goal is not to optimize every interaction in real time. The goal is to create a controlled, auditable improvement loop.

## Anti-Patterns

### Auto Mutation

Bad:

```text
event stream
  -> update SearchProfile aliases directly
```

Why it is bad:

- breaks determinism
- hides cause of changes
- makes regressions hard to reproduce
- makes rollback unclear

### Silent Ranking Drift

Bad:

```text
clicks
  -> change weights automatically
  -> new ranking behavior
```

Why it is bad:

- ranking changes become hard to explain
- baseline comparisons become unreliable
- feedback loops can self-reinforce bad results

### Keystroke Analytics

Bad:

```text
p
pl
pla
play
plays
```

Why it is bad:

- high volume
- low intent quality
- adds noise to insights
- repeats the load pattern removed from live search

## Minimal Data Model

Future Slice F storage:

```text
search_interaction_events
-------------------------
id
query
normalized_query
surface
results_count
top_result_id
top_match_reason
selected_result_id
selected_rank
metadata
created_at

search_query_insights
---------------------
id
normalized_query
insight_type
query_count
zero_result_count
low_result_count
click_count
top_clicked_identity_id
confidence
evidence
last_seen_at
created_at
updated_at

search_improvement_recommendations
----------------------------------
id
insight_id
recommendation_type
status
proposed_change
evidence
reviewed_by
reviewed_at
created_at
updated_at
```

Recommended statuses:

```text
open
approved
rejected
deferred
applied
```

## Rule-Backed Insight Examples

Zero-result hotspot:

```text
if query_count >= N
and zero_result_rate >= threshold
then emit ZERO_RESULT_HOTSPOT
```

Alias gap:

```text
if query_count >= N
and selected clicks concentrate on one identity
and query terms are missing from that identity aliases
then emit ALIAS_GAP
```

Coverage gap:

```text
if query_count >= N
and no matching brand+region profile exists
then emit COVERAGE_GAP
```

Ranking gap:

```text
if top result is rarely clicked
and lower-ranked result is frequently selected
then emit RANKING_GAP
```

## Observability

Feedback health:

```text
events_total
insights_total
recommendations_open
recommendations_approved
recommendations_rejected
recommendations_applied
```

Insight quality:

```text
zero_result_hotspots
alias_gaps
coverage_gaps
ranking_gaps
brand_mismatches
region_mismatches
```

Processing performance:

```text
events_aggregated
aggregation_duration_ms
recommendations_generated
```

## Relationship To SearchProfile

SearchProfile provides a stable representation layer.

Feedback Intelligence provides controlled evolution of that representation.

The only valid path from feedback to SearchProfile is:

```text
events
  -> aggregate
  -> insight
  -> recommendation
  -> approval
  -> profile rule or catalog fact change
  -> SearchProfile rebuild
```

This preserves:

- reproducibility
- explainability
- auditability
- rollback
- deterministic search behavior

## Migration Path

Phase 1:

- Add append-only `search_interaction_events`.
- Emit completed suggest/full-search interactions only.

Phase 2:

- Aggregate events into `search_query_insights`.
- Classify zero-result, alias, coverage, ranking, brand, and region gaps.

Phase 3:

- Generate `search_improvement_recommendations`.
- Keep recommendations unapplied by default.

Phase 4:

- Add review/approval workflow.
- Apply approved changes to curated dictionaries, mappings, overrides, or ranking configuration.

Phase 5:

- Rebuild SearchProfiles and compare against baseline/comparison artifacts.

## Future Discovery Layer

Once feedback insights are available, SearchProfile can support discovery use cases:

- related items
- substitutes
- nearby regions
- popular alternatives
- regional bundles

Discovery should be built on top of SearchProfile and feedback insights, not by bypassing the deterministic model layer.
