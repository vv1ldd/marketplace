# Public Localization Contract

## Contract

Public UI copy is translated. Catalog content is data.

```text
LocaleContext
  -> lang/en.json + lang/ru.json
  -> public rendering
```

Locale controls presentation. Locale does not mutate commerce, catalog scope, pricing scope, storage prices, settlement, or order truth.

## Translated

Public runtime UI copy must come from Laravel translations:

- navigation
- buttons
- labels
- headings
- empty states
- public validation messages
- checkout helper text
- vault and safe helper text
- public UI copy in Blade, public JSON, emails, and public API responses

Use flat JSON keys in:

- `lang/en.json`
- `lang/ru.json`

Example:

```blade
{{ __('storefront.footer.marketplace') }}
@lang('catalog.filters.brand')
```

## Not Translated As UI

These are data and must not be treated as interface copy:

- product titles
- product descriptions
- seller content
- catalog facts
- user-generated content
- provider payloads
- legal entity data
- persisted order text

If a page renders these values, it may show any language stored in the data source.

## Public Surfaces

The public UI copy rule applies first to:

- `resources/views/storefront/**`
- `resources/views/catalog/**`
- `resources/views/products/**`
- `resources/views/network/**`
- `resources/views/landing.blade.php`
- shared public partials included by those pages

Partner, ops, admin, console, generated SDKs, vendor code, docs, tests, and fixtures are separate scopes.

## Guardrail

`PublicLocalizationGuardrailTest` protects public rendering from new hardcoded Cyrillic UI copy.

The guardrail intentionally starts with a legacy baseline for files that are not migrated yet. Migrated files should have a zero-Cyrillic baseline. Each migration should reduce the baseline until public Blade surfaces are fully guarded.

## Supported Locales

Supported locale means tested UI coverage.

During the first public i18n rollout, expose only:

```text
en
ru
```

Additional locales can be re-enabled after their public UI translation coverage is explicit and tested.
