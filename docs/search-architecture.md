# Search Architecture

## Context

The storefront search path was originally request-heavy:

```text
Request
  -> inspect catalog tables
  -> build catalog cards
  -> render HTML
  -> respond
```

Recent search work moved expensive operations out of the hot path:

- `Schema::hasTable()` checks were memoized for the request lifetime.
- Live-search keystrokes no longer run search logging, query understanding, or attribution.
- Typing now calls a lightweight `/store/suggest` endpoint, while full search remains a submit action.

The next search evolution should preserve the same direction of computation:

```text
Catalog change
  -> normalize facts
  -> materialize SearchProfile
  -> store projection

Request
  -> read SearchProfile
  -> rank
  -> respond
```

## Decision

Introduce a dedicated search bounded context around a materialized read projection:

```text
Catalog Facts
  -> SearchProfileBuilder
  -> SearchProfile
  -> SearchRankingEngine
  -> Suggest/Search API
```

`CanonicalProductIdentity` remains the catalog fact source. Search-specific knowledge is stored separately in `canonical_product_search_profiles`.

## Invariants

### Invariant 1

SearchProfile is write-time materialized.

Search and Suggest must not build aliases, tokens, or metadata during request execution.

### Invariant 2

SearchProfile is deterministic.

The same catalog facts must always produce the same SearchProfile.

The builder must not depend on the current request, user, session, time, popularity, conversion, click-through, or other runtime signals.

### Invariant 3

SearchProfile contains facts.

SearchRankingEngine contains decisions.

Examples:

```text
SearchProfile
  -> brand
  -> aliases
  -> tokens
  -> region
  -> category

SearchRankingEngine
  -> score
  -> score_breakdown
  -> match_reason
  -> ordering
```

## Non-Goals

SearchProfile is not a ranking model.

SearchProfile must not contain:

- score
- score_breakdown
- match_reason
- popularity
- conversion metrics
- click-through metrics
- request-specific boosts

These belong to SearchRankingEngine or analytics-derived ranking signals.

SearchProfile is not built during request execution.

Suggest/Search requests may read SearchProfiles, but may not generate or mutate them.

This document does not introduce embeddings, vector search, LLM query rewriting, FULLTEXT, Meilisearch, or Typesense. Those can be added later as retrieval/index layers that consume the same SearchProfile projection.

## Data Model

Minimum Slice A table:

```text
canonical_product_search_profiles
---------------------------------
id
canonical_product_identity_id
search_text
search_tokens
search_aliases
search_metadata
profile_version
last_rebuild_at
last_error
created_at
updated_at
```

Recommended profile shape:

```json
{
  "search_text": "Sony PlayStation 5 Console USA",
  "search_tokens": ["sony", "playstation", "5", "console", "usa"],
  "search_aliases": {
    "brand": ["sony"],
    "product": ["ps5", "ps 5", "play station", "play station 5"],
    "category": ["console", "gaming console"],
    "region": ["usa", "us", "united states"]
  },
  "search_metadata": {
    "brand": "Sony",
    "region": "US",
    "currency": "USD",
    "face_value": null,
    "category": "gaming_consoles",
    "signals": {
      "popularity": 0,
      "conversion_rate": 0,
      "manual_boost": 0
    }
  },
  "profile_version": 1
}
```

`search_tokens` and `search_aliases` are intentionally separate:

- `search_tokens` are normalized indexable facts.
- `search_aliases` are rewrite and explainability facts.

Runtime responses may include ranking output, but that output is not stored in the profile:

```json
{
  "name": "Sony PlayStation 5 Console US",
  "url": "...",
  "score": 92,
  "match_reason": "ALIAS_MATCH",
  "matched_alias": "ps5",
  "score_breakdown": {
    "alias": 60,
    "brand": 20,
    "region": 12
  }
}
```

## Builder

`CanonicalProductSearchProfileBuilder::rebuild(CanonicalProductIdentity $identity)` must be idempotent and replace the full profile.

It must not patch or append to the existing profile:

```text
catalog facts
  -> build complete profile
  -> replace stored profile
```

This keeps rebuilds debuggable and makes profile version changes safe.

## Rebuild Commands

Provide three rebuild modes:

```bash
php artisan search-profile:rebuild
php artisan search-profile:rebuild --identity=123
php artisan search-profile:rebuild --stale
```

- No option rebuilds the full catalog.
- `--identity` rebuilds one identity for debugging.
- `--stale` rebuilds missing or outdated profiles.

SearchProfile rebuilds should be triggered from the write path, for example:

```text
CanonicalProductIdentityUpdated
  -> RebuildSearchProfile
```

The first implementation may run synchronously. The contract should allow moving the handler to a queue later without changing request behavior.

## Observability

Profile health:

```text
profiles_total
profiles_missing
profiles_stale
profiles_failed
profile_version_distribution
last_rebuild_at
```

Profile build performance:

```text
rebuild_duration_ms
profiles_rebuilt
profiles_rebuilt_per_minute
```

These metrics should answer two operational questions:

- Do all searchable catalog identities have usable profiles?
- How expensive is a full or stale-only rebuild?

## Suggest Analytics

Suggest analytics should capture completed interactions, not every keystroke.

Recommended event contract:

```text
query
results_count
top_match_reason
selected_result_id
selected_rank
```

Do not record the intermediate stream:

```text
p
pl
pla
play
plays
```

This keeps analytics useful for relevance tuning without returning keystroke load to the hot path.

## Migration Path

Phase 1:

- Add `canonical_product_search_profiles`.
- Add `CanonicalProductSearchProfileBuilder`.
- Add rebuild commands and metrics.

Phase 2:

- Make `/store/suggest` read SearchProfile.
- Add completed suggest interaction analytics.

Phase 3:

- Add a simple query parser for brand, region, currency, face value, price, category, and keywords.

Phase 4:

- Move ranking decisions into SearchRankingEngine.
- Return `match_reason` and `score_breakdown` from ranking responses.

Phase 5:

- Add FULLTEXT, Meilisearch, Typesense, or another retrieval layer.
- Keep SearchProfile as the stable search knowledge projection.

The search engine may change. The SearchProfile model should survive engine replacement.
