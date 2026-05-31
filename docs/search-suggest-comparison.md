# Search Suggest Comparison

Comparison after Slice C: `/store/suggest` reads materialized SearchProfiles and applies deterministic lightweight ranking.

No parser, full-search changes, analytics changes, or external search engine were introduced.

## Runtime Path

```text
/store/suggest
  -> canonical_product_search_profiles
  -> lightweight ranking
  -> current JSON suggestions contract
```

SearchProfile version used for this comparison: `3`.

Profile health at capture time:

```text
profiles_total: 12657
profiles_missing: 0
profiles_stale: 0
profiles_failed: 0
profile_version_distribution: {"3":12657}
```

## Evaluation Results

| Query | Before Top Result | After Top Result | Before Results | After Results | Before Latency (ms) | After Latency (ms) | Result | Notes |
| --- | --- | --- | ---: | ---: | ---: | ---: | --- | --- |
| ps5 | Playstation Uk 10 GBP GB | Playstation 5 USD ОМАН | 8 | 1 | 118 | 110 | PASS | PS5 now uses PS5-specific aliasing instead of broad PlayStation cards. |
| psn | Playstation Uk 10 GBP GB | Playstation Uk 100 GBP GB | 8 | 8 | 84 | 52 | PASS | PlayStation/PSN family retrieval preserved. |
| steam ar | Steam 30 EUR ЕВРОПА | Steam Uae 49 AED AE | 8 | 8 | 63 | 87 | KNOWN GAP | No Steam+AR profile exists in the current catalog projection. |
| steam argentina | - | Steam Uae 49 AED AE | 0 | 8 | 53 | 90 | PARTIAL | Zero-result case fixed, but no Steam+Argentina profile exists to rank first. |
| spotify turkey | - | Spotify Months Uae 190 AED AE | 0 | 8 | 47 | 109 | PARTIAL | Zero-result case fixed; no Spotify+TR profile exists in current catalog projection. |
| spotify tr | - | Spotify Months Uae 190 AED AE | 0 | 8 | 47 | 104 | PARTIAL | Zero-result case fixed; no Spotify+TR profile exists in current catalog projection. |
| xbox usa | Steam Bahrain 10 USD | Xbox Usa 25 USD US | 8 | 8 | 53 | 55 | PASS | Brand hard gate prevents Steam/USD from outranking Xbox+US. |
| switch oled | - | Nintendo Mortal Kombat Switch 45 USD GLOBAL | 0 | 8 | 47 | 70 | PASS | Switch alias is recognized and retrieves Nintendo/Switch results. |
| nintendo switch | Nintendo Mortal Kombat Switch 45 USD | Nintendo Mortal Kombat Switch 45 USD GLOBAL | 8 | 8 | 48 | 69 | PASS | No retrieval regression for Nintendo/Switch. |
| gift card usa | Ruths Chris Steakhouse Ruth S 25 USD | IKEA Usa 25 USD US | 8 | 8 | 55 | 28 | PASS | US/USA region preference is now visible in top result. |

## Catalog Availability Checks

Observed profile counts for cases where the expected exact regional match is unavailable:

```text
Steam + AR profiles: 0
Spotify + TR profiles: 0
Xbox + US profiles: 32
```

The remaining Steam/Spotify regional gaps are therefore catalog/profile coverage issues, not ranking failures in this slice.

## Ranking Rules Used

Initial deterministic weights:

```text
Alias exact/prefix match  +60
Brand match               +20
Region match              +15
Category match            +10
Token match                +5 each, capped at +25
Brand mismatch            -100
Region mismatch           -10
```

Hard gate:

```text
If a brand is explicitly requested, a candidate with a different brand receives a strong penalty.
```

This is why `xbox usa` can no longer rank Steam results above Xbox results based on weak USD/token overlap.

## Summary

Slice C improved the main baseline failures without changing the public JSON shape:

- `xbox usa` now ranks Xbox+US first.
- `spotify turkey` and `spotify tr` no longer return zero results.
- `switch oled` no longer returns zero results.
- `ps5` now prefers a PS5-specific profile.
- Existing `psn` and `nintendo switch` behavior did not regress.

Remaining quality work should be driven by catalog coverage and the next planned layer, Query Parser, rather than more latency work.
