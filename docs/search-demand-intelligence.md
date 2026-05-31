# Search Demand Intelligence

## Context

SearchProfile stabilizes how the catalog is represented for retrieval. The next system boundary is external demand: queries observed in search engines, trend tools, autocomplete APIs, and manual market research exports.

External signals describe reality. SearchProfile describes product space. They converge only through interpretation and approval.

## Decision

Introduce Search Demand Intelligence as a separate bounded context. It observes external demand, imports it into a neutral signal table, compares those signals with the current SearchProfile, and produces an insight report.

The first pipeline is deliberately small:

```text
external_search_query_signals
        ↓
search-signals:import
        ↓
search-signals:analyze
        ↓
insight report
```

This pipeline never mutates SearchProfile, ranking, catalog facts, or aliases directly.

## Data Model

`external_search_query_signals` stores raw observations:

- `query`
- `normalized_query`
- `source`
- `country`
- `locale`
- `impressions`
- `clicks`
- `ctr`
- `volume`
- `landing_url`
- `observed_at`
- `metadata`

`signal_hash` makes imports idempotent for reproducible JSON/CSV replays.

## Commands

Import a JSON or CSV export:

```bash
php artisan search-signals:import path/to/signals.json
php artisan search-signals:import path/to/signals.csv --source=google_search_console
```

Pull from external search systems:

```bash
php artisan search-signals:pull google_search_console --from=2026-05-01 --to=2026-05-31
php artisan search-signals:pull yandex_webmaster --from=2026-05-01 --to=2026-05-31
php artisan search-signals:pull google_suggest --query="playstation" --locale=ru
php artisan search-signals:pull yandex_suggest --query="плейстейшн" --locale=ru
```

Analyze external demand against the current SearchProfile:

```bash
php artisan search-signals:analyze --limit=25 --days=90
php artisan search-signals:analyze --json
```

## Insight Types

- `COVERED`: current SearchProfile-backed suggest covers the observed demand.
- `COVERAGE_GAP`: external demand exists and no current result is found.
- `ALIAS_GAP`: product-space hints exist, but the query does not resolve.
- `RANKING_GAP`: the expected entity appears, but not as the top result.
- `BRAND_GAP`: the observed demand implies a brand that is not represented by the top result.
- `REGION_GAP`: the observed demand implies a product region that is not represented by the top result.
- `LOW_COVERAGE`: the query resolves, but assortment depth is thin.

## Invariants

- External signals are observations, not facts about the catalog.
- Interpretation is mandatory before mutation.
- No external signal can affect SearchProfile without explicit approval.
- SDK integrations are adapters into `external_search_query_signals`, not search engines.
- The analyzer emits reports and recommendations only.

## Future Adapters

The first slice intentionally supports JSON/CSV before SDK integrations. Future adapters can map provider-specific payloads into the same signal model:

- Google Search Console
- Yandex Webmaster
- Google Trends
- Yandex Wordstat
- Google/Yandex/Bing autocomplete
- Provider or marketplace suggest exports

## External Search Adapters

Search engine integrations are adapters into `external_search_query_signals`. They do not replace retrieval and do not write to SearchProfile.

The first connected providers are:

- `google_search_console`: uses Search Console Search Analytics API.
- `yandex_webmaster`: uses Yandex Webmaster popular search queries API.
- `google_suggest`: imports autocomplete suggestions for a seed query.
- `yandex_suggest`: imports autocomplete suggestions for a seed query.

Required configuration:

```dotenv
GOOGLE_SEARCH_CONSOLE_SITE_URL=
GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN=
YANDEX_WEBMASTER_OAUTH_TOKEN=
YANDEX_WEBMASTER_USER_ID=
YANDEX_WEBMASTER_HOST_ID=
```

Safe validation flow:

```bash
php artisan search-signals:pull google_suggest --query="steam" --dry-run --json
php artisan search-signals:pull google_search_console --dry-run --json
php artisan search-signals:pull google_search_console
php artisan search-signals:analyze
php artisan search-signals:recommend
```

## Decision Intelligence

Demand Intelligence answers where the external world and internal model diverge. Decision Intelligence ranks possible interventions for those divergences.

The decision chain is:

```text
ExternalSearchQuerySignal
        ↓
Search Demand Intelligence insight
        ↓
SearchDemandRecommendation
        ↓
approved / rejected / applied decision
```

`SearchDemandRecommendation` does not mutate catalog facts, ranking, aliases, or SearchProfile. It stores proposed interventions with an action type, impact score, confidence, evidence, expected entity, and status.

Action types form the first action ontology:

- `ADD_PRODUCT`
- `ADD_ALIAS`
- `ADD_REGION_VARIANT`
- `IMPROVE_RANKING`
- `IMPROVE_SUPPLY`
- `CREATE_COLLECTION`
- `OPEN_PARTNER_OPPORTUNITY`

The first command is:

```bash
php artisan search-signals:recommend --limit=25 --days=90
php artisan search-signals:recommend --json
```

Decision Intelligence invariant:

```text
Insights describe reality gaps.
Recommendations describe possible interventions.
Decisions determine allowed changes.
```
