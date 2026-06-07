'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useRef, useState } from 'react';
import { fetchStorefrontCatalog, fetchStorefrontSuggestions } from '../lib/storefront-api';

const CURRENCY_TERMS = new Set([
  'usd',
  'eur',
  'gbp',
  'rub',
  'try',
  'tl',
  'uah',
  'kzt',
  'gel',
]);

function catalogHref(query) {
  const trimmed = query.trim();

  return trimmed ? `/?q=${encodeURIComponent(trimmed)}` : '/';
}

function localHref(href, fallback = '/') {
  if (!href) {
    return fallback;
  }

  try {
    const url = new URL(href);
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return href;
  }
}

function productDetailHref(href, fallback = '/') {
  const local = localHref(href, fallback);
  const match = local.match(/^\/catalog\/products\/([^/?#]+)(.*)?$/);

  if (match) {
    return `/products/${match[1]}${match[2] || ''}`;
  }

  return local;
}

function appendQuery(href, query = {}) {
  const url = new URL(href, 'https://meanly.local');

  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return `${url.pathname}${url.search}${url.hash}`;
}

function normalizeAmount(value) {
  const number = Number.parseFloat(String(value).replace(',', '.'));

  if (!Number.isFinite(number)) {
    return null;
  }

  return Number.isInteger(number) ? String(number) : String(number).replace(/0+$/, '').replace(/\.$/, '');
}

function parseNominalLabel(value = '') {
  const match = String(value).trim().match(/^(\d+(?:[.,]\d+)?)\s*([a-z]{3})?$/i);

  if (!match) {
    return null;
  }

  return {
    faceValue: normalizeAmount(match[1]),
    currency: match[2] ? match[2].toUpperCase() : null,
  };
}

function nominalFromQuery(query = '') {
  const text = query.replace(/[$€£]/g, ' ');
  const withCurrency = text.match(/(\d+(?:[.,]\d+)?)\s*([a-z]{3})\b/i);

  if (withCurrency) {
    return {
      faceValue: normalizeAmount(withCurrency[1]),
      currency: withCurrency[2].toUpperCase(),
    };
  }

  const amountOnly = text.match(/\b(\d+(?:[.,]\d+)?)\b/);

  if (!amountOnly) {
    return null;
  }

  return {
    faceValue: normalizeAmount(amountOnly[1]),
    currency: null,
  };
}

function matchingGroupNominal(group, query) {
  const nominal = nominalFromQuery(query);

  if (!nominal?.faceValue) {
    return null;
  }

  return (group.variant_group?.nominals || [])
    .map(parseNominalLabel)
    .find((candidate) => (
      candidate?.faceValue === nominal.faceValue
      && (!nominal.currency || candidate.currency === nominal.currency)
    )) || null;
}

function normalizedSearchValue(value = '') {
  return normalizedKey(value).replace(/[-_]+/g, ' ').replace(/\s+/g, ' ');
}

function queryTerms(query = '') {
  return normalizedSearchValue(query).split(/\s+/).filter(Boolean);
}

function textMatchesQuery(value = '', query = '') {
  const candidate = normalizedSearchValue(value);
  const phrase = normalizedSearchValue(query);

  if (!candidate || !phrase) {
    return false;
  }

  if (phrase === candidate || queryTerms(query).includes(candidate)) {
    return true;
  }

  return candidate.length > 2 && phrase.includes(candidate);
}

function optionValues(option) {
  if (typeof option === 'string') {
    return [option];
  }

  return [
    option?.value,
    option?.name,
    option?.label,
    option?.slug,
  ].filter(Boolean);
}

function optionLabel(option) {
  if (typeof option === 'string') {
    return option;
  }

  return option?.label || option?.name || option?.value || '';
}

function matchingGroupRegion(group, query) {
  return (group.variant_group?.regions || [])
    .map((region) => ({
      region,
      values: optionValues(region),
    }))
    .find((candidate) => candidate.values.some((value) => textMatchesQuery(value, query)))?.region || null;
}

function productHref(product, query) {
  const isGrouped = product.variant_group?.is_grouped;
  const fallback = product.slug ? `/products/${product.slug}` : '/';
  const href = isGrouped ? productDetailHref(product.links?.self, fallback) : fallback;
  const matchedNominal = product.variant_group?.is_grouped ? matchingGroupNominal(product, query) : null;
  const matchedRegion = product.variant_group?.is_grouped ? matchingGroupRegion(product, query) : null;

  if (matchedNominal || matchedRegion) {
    return appendQuery(href, {
      face_value: matchedNominal?.faceValue,
      currency: matchedNominal?.currency,
      region: optionLabel(matchedRegion),
    });
  }

  return href;
}

function suggestionHref(result) {
  return productDetailHref(result.url, result.slug ? `/products/${result.slug}` : '/');
}

function normalizedKey(value = '') {
  return String(value).trim().toLowerCase();
}

function collectBrandSuggestions(results = [], catalog = {}, query = '') {
  const seen = new Set();
  const resultBrands = results.map((result) => ({
    label: String(result.brand || '').trim(),
    count: null,
  }));
  const catalogBrands = (catalog.brands || []).map((brand) => ({
    label: String(brand.label || brand.name || brand.slug || '').trim(),
    count: brand.count,
  }));

  return [...resultBrands, ...catalogBrands]
    .filter((brand) => brand.label)
    .filter((brand) => {
      const key = normalizedKey(brand.label);
      if (seen.has(key)) return false;
      seen.add(key);

      return true;
    })
    .slice(0, 3)
    .map((brand) => ({
      key: `brand-${normalizedKey(brand.label)}`,
      label: brand.label,
      href: catalogHref(brand.label),
      type: 'Brand',
      meta: brand.count ? `${brand.count} products` : (query ? `Browse ${brand.label} matches` : 'Browse brand'),
      priority: 30,
    }));
}

function collectValueSuggestion(query = '') {
  const nominal = nominalFromQuery(query);
  if (!nominal?.faceValue) {
    return [];
  }

  return [{
    key: `value-${nominal.faceValue}-${nominal.currency || 'any'}`,
    label: nominal.currency ? `${nominal.faceValue} ${nominal.currency}` : nominal.faceValue,
    href: catalogHref(query),
    type: 'Value',
    meta: nominal.currency ? 'Filter by nominal and currency' : 'Filter by nominal',
    priority: 20,
  }];
}

function collectCategorySuggestions(catalog = {}, query = '') {
  return (catalog.categories || []).slice(0, 3).map((category) => ({
    key: `category-${category.slug || category.name}`,
    label: category.label || category.name,
    href: localHref(category.links?.self, category.slug ? `/catalog/${category.slug}` : catalogHref(query)),
    type: 'Category',
    meta: category.count ? `${category.count} products` : 'Browse category',
    priority: 10,
  }));
}

function productMeta(product, query) {
  const matchedNominal = product.variant_group?.is_grouped ? matchingGroupNominal(product, query) : null;
  const matchedRegion = product.variant_group?.is_grouped ? matchingGroupRegion(product, query) : null;

  if (matchedNominal || matchedRegion) {
    return [
      matchedRegion ? `Region ${optionLabel(matchedRegion)}` : null,
      matchedNominal ? `${matchedNominal.faceValue} ${matchedNominal.currency}` : null,
    ].filter(Boolean).join(' · ');
  }

  if (product.variant_group?.is_grouped) {
    return [
      product.variant_group.variant_count ? `${product.variant_group.variant_count} variants` : null,
      product.variant_group.region_count ? `${product.variant_group.region_count} regions` : null,
      product.variant_group.nominal_count ? `${product.variant_group.nominal_count} nominals` : null,
    ].filter(Boolean).join(' · ');
  }

  return [
    product.face_value && product.face_value_currency ? `${normalizeAmount(product.face_value)} ${product.face_value_currency}` : null,
    product.region || 'global',
  ].filter(Boolean).join(' · ');
}

function collectSuggestions(catalog = {}, query = '') {
  const normalizedQuery = query.trim();
  const groupedBrowse = (catalog.products?.browse || []).filter((product) => product.variant_group?.is_grouped);
  const groups = [
    ...(catalog.product_groups || catalog.products?.groups || []),
    ...groupedBrowse,
  ].slice(0, 4).map((group) => {
    const hasRegion = Boolean(matchingGroupRegion(group, normalizedQuery));
    const hasNominal = Boolean(matchingGroupNominal(group, normalizedQuery));
    const type = hasRegion && hasNominal
      ? 'Region + nominal group'
      : hasRegion
        ? 'Region group'
        : hasNominal
          ? 'Nominal group'
          : 'Product group';

    return {
      key: `group-${group.id || group.slug || group.name}`,
      label: group.name,
      href: productHref(group, normalizedQuery),
      type,
      meta: productMeta(group, normalizedQuery),
    };
  });
  const products = [
    ...(catalog.products?.featured || []),
    ...(catalog.products?.browse || []),
    ...(catalog.products?.provider_network || []),
  ].filter((product) => !product.variant_group?.is_grouped).slice(0, 4).map((product) => ({
    key: `product-${product.id || product.slug || product.name}`,
    label: product.name,
    href: productHref(product, normalizedQuery),
    type: product.category?.label || 'Product',
    meta: productMeta(product, normalizedQuery),
  }));
  const categories = (catalog.categories || []).slice(0, 3).map((category) => ({
    key: `category-${category.slug || category.name}`,
    label: category.label || category.name,
    href: localHref(category.links?.self, category.slug ? `/catalog/${category.slug}` : catalogHref(normalizedQuery)),
    type: 'Category',
    meta: category.count ? `${category.count} products` : 'Browse category',
  }));

  return [
    ...groups,
    ...products,
    ...categories,
  ].filter((suggestion) => suggestion.label && suggestion.href).slice(0, 8);
}

function catalogRegionOptions(catalog = {}) {
  const groups = [
    ...(catalog.product_groups || catalog.products?.groups || []),
    ...(catalog.products?.browse || []).filter((product) => product.variant_group?.is_grouped),
  ];
  const products = [
    ...(catalog.products?.featured || []),
    ...(catalog.products?.browse || []),
    ...(catalog.products?.provider_network || []),
  ];

  return [
    ...groups.flatMap((group) => group.variant_group?.regions || []),
    ...products.map((product) => product.region).filter(Boolean),
  ];
}

function queryHasCurrencyMeta(query = '') {
  return queryTerms(query).some((term) => CURRENCY_TERMS.has(term));
}

function queryHasRegionMeta(query = '', catalog = {}) {
  return catalogRegionOptions(catalog).some((region) => (
    optionValues(region).some((value) => textMatchesQuery(value, query))
  ));
}

function queryHasSpecificProductMeta(query = '', catalog = {}) {
  return Boolean(
    nominalFromQuery(query)?.faceValue
    || queryHasCurrencyMeta(query)
    || queryHasRegionMeta(query, catalog)
  );
}

function resolverPriority(result, hasSpecificProductMeta) {
  if (hasSpecificProductMeta) {
    return result.availability === 'available' ? 105 : 95;
  }

  return result.availability === 'available' ? 70 : 60;
}

function fallbackPriority(suggestion, hasSpecificProductMeta) {
  if (suggestion.type === 'Region + nominal group') {
    return 150;
  }

  if (suggestion.type === 'Region group' || suggestion.type === 'Nominal group') {
    return 140;
  }

  if (suggestion.type === 'Product group') {
    return hasSpecificProductMeta ? 120 : 130;
  }

  return hasSpecificProductMeta ? 90 : 65;
}

function collectResolverSuggestions({ catalog = {}, suggestionPayload = {}, query = '' }) {
  const results = suggestionPayload.results || [];
  const hasSpecificProductMeta = queryHasSpecificProductMeta(query, catalog);
  const productSuggestions = results.slice(0, 6).map((result) => ({
    key: `resolver-${result.id || result.url || result.name}`,
    label: result.name,
    href: suggestionHref(result),
    type: 'Product match',
    meta: [result.match_label, result.category, result.brand].filter(Boolean).join(' · '),
    priority: resolverPriority(result, hasSpecificProductMeta),
  }));
  const fallbackSuggestions = collectSuggestions(catalog, query).map((suggestion) => ({
    ...suggestion,
    priority: fallbackPriority(suggestion, hasSpecificProductMeta),
  }));
  const shortcuts = [
    ...collectBrandSuggestions(results, catalog, query),
    ...collectValueSuggestion(query),
    ...collectCategorySuggestions(catalog, query),
  ];
  const seen = new Set();

  return [...productSuggestions, ...fallbackSuggestions, ...shortcuts]
    .filter((suggestion) => suggestion.label && suggestion.href)
    .sort((left, right) => (right.priority || 0) - (left.priority || 0))
    .filter((suggestion) => {
      const key = `${suggestion.type}:${suggestion.href}:${normalizedKey(suggestion.label)}`;
      if (seen.has(key)) return false;
      seen.add(key);

      return true;
    })
    .slice(0, 10);
}

export function AskSearchBox({ initialQuery = '' }) {
  const router = useRouter();
  const [query, setQuery] = useState(initialQuery);
  const [suggestions, setSuggestions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isDismissed, setIsDismissed] = useState(false);
  const requestId = useRef(0);

  useEffect(() => {
    setQuery(initialQuery);
    setIsDismissed(false);
  }, [initialQuery]);

  useEffect(() => {
    const trimmed = query.trim();

    if (trimmed.length < 2) {
      setSuggestions([]);
      setIsLoading(false);
      return;
    }

    const currentRequest = requestId.current + 1;
    requestId.current = currentRequest;
    setIsLoading(true);

    const timeout = window.setTimeout(async () => {
      try {
        const [catalog, suggestionPayload] = await Promise.all([
          fetchStorefrontCatalog(trimmed),
          fetchStorefrontSuggestions(trimmed),
        ]);

        if (requestId.current === currentRequest) {
          setSuggestions(collectResolverSuggestions({ catalog, suggestionPayload, query: trimmed }));
          setIsDismissed(false);
        }
      } catch {
        if (requestId.current === currentRequest) {
          setSuggestions(collectSuggestions({}, trimmed));
          setIsDismissed(false);
        }
      } finally {
        if (requestId.current === currentRequest) {
          setIsLoading(false);
        }
      }
    }, 220);

    return () => window.clearTimeout(timeout);
  }, [query]);

  const showDropdown = !isDismissed && query.trim().length >= 2;
  const handleSubmit = (event) => {
    const trimmed = query.trim();

    if (!trimmed) {
      return;
    }

    event.preventDefault();
    setIsDismissed(true);
    router.push(`/meanly-ai?q=${encodeURIComponent(trimmed)}`);
  };

  return (
    <div className="ask-search">
      <form action="/meanly-ai" className="search ask-search__form" onSubmit={handleSubmit}>
        <input
          autoComplete="off"
          name="q"
          onChange={(event) => {
            setQuery(event.target.value);
            setIsDismissed(false);
          }}
          placeholder="Search products or ask Meanly"
          value={query}
        />
        <button type="submit">Ask</button>
      </form>
      {showDropdown ? (
        <div className="ask-suggestions" onMouseDown={(event) => event.preventDefault()}>
          {isLoading ? <p className="ask-suggestions__status">Looking through catalog...</p> : null}
          {!isLoading && suggestions.length === 0 ? (
            <p className="ask-suggestions__status">No direct match yet. Press Enter to ask Meanly.</p>
          ) : null}
          {suggestions.map((suggestion) => (
            <Link
              className="ask-suggestion"
              href={suggestion.href}
              key={suggestion.key}
              onClick={() => setIsDismissed(true)}
            >
              <span>{suggestion.type}</span>
              <strong>{suggestion.label}</strong>
              {suggestion.meta ? <p>{suggestion.meta}</p> : null}
            </Link>
          ))}
        </div>
      ) : null}
    </div>
  );
}
