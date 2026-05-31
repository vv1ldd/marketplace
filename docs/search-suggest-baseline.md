# Search Suggest Baseline

Captured before SearchProfile runtime adoption.

Current runtime path:

```text
/store/suggest
  -> lightweight canonical identity query
  -> JSON suggestions
```

SearchProfile storage and rebuild lifecycle exist, but `/store/suggest` does not read `canonical_product_search_profiles` yet.

## Evaluation Set

| Query | Results | Top Result | Latency (ms) | Notes |
| --- | ---: | --- | ---: | --- |
| ps5 | 8 | Playstation Uk 10 GBP GB | 118 | Relevant brand, but generic PlayStation card rather than PS5-specific intent. |
| psn | 8 | Playstation Uk 10 GBP GB | 84 | Acceptable PlayStation/PSN family match. |
| steam ar | 8 | Steam 30 EUR ЕВРОПА | 63 | Region mismatch: AR/Argentina intent resolves to generic Europe result. |
| steam argentina | 0 | - | 53 | No results despite a clear Steam + Argentina intent. |
| spotify turkey | 0 | - | 47 | No results; should prefer Spotify/TR if catalog has matching products. |
| spotify tr | 0 | - | 47 | No results; TR alias is not understood by current suggest. |
| xbox usa | 8 | Steam Bahrain 10 USD | 53 | Brand mismatch: weak USD/token match beats Xbox + USA intent. |
| switch oled | 0 | - | 47 | No results; Nintendo Switch OLED aliases are not understood. |
| nintendo switch | 8 | Nintendo Mortal Kombat Switch 45 USD | 48 | Relevant Nintendo/Switch family result. |
| gift card usa | 8 | Ruths Chris Steakhouse Ruth S 25 USD | 55 | Broad gift-card result; USA/US preference is not explicit. |

## Expected Improvements

`xbox usa`

- Xbox result ranked #1.
- US/USA region preferred.
- Non-Xbox results should not win from USD/token matches alone.

`spotify turkey` / `spotify tr`

- Non-zero results if catalog contains Spotify TR/Turkey products.
- `tr`, `turkey`, and `turkiye` aliases should map to the same region intent.

`switch oled`

- Nintendo Switch OLED aliases recognized.
- Nintendo/Switch results should be returned even if the exact words are not present in `identity_slug`.

`steam ar` / `steam argentina`

- Argentina/AR results preferred over generic Steam or Europe results when present.
- Query rewrite should expand `ar` and `argentina` consistently.

`ps5` / `psn`

- No regression in PlayStation family retrieval.
- `ps5` should only boost PlayStation 5-specific profiles, not every PlayStation card.
- `psn` may continue matching the broader PlayStation payment-card family.

`gift card usa`

- Gift-card results should remain broad.
- US/USA region should contribute positive ranking when available.

## Slice C Evaluation Rule

After `/store/suggest` reads SearchProfile, run the exact same evaluation set and compare:

```text
query
results_count
top_result
latency_ms
notes
```

Success is not only lower latency. The primary goal is better retrieval/relevance:

- Fewer zero-result cases for clear aliases.
- Fewer brand mismatches.
- Better region preference.
- No regression on already acceptable PlayStation/Nintendo cases.

## Candidate Lightweight Ranking Weights

Start conservatively:

```text
Alias exact match  +60
Brand match        +20
Region match       +15
Category match     +10
Token match         +5
Brand mismatch    -100
```

These weights are a starting point for Slice C, not a permanent ranking model.

`score`, `score_breakdown`, `match_reason`, and ordering belong to SearchRankingEngine runtime decisions. They must not be stored in SearchProfile.
