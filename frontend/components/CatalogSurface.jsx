'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AskSearchBox } from './AskSearchBox';
import { fetchStorefrontCatalog } from '../lib/storefront-api';

const DEFAULT_QUICK_CHIPS = [
  'Steam Turkey',
  'PlayStation US',
  'Spotify subscription',
  'Xbox gift card',
  '20 EUR card',
];

function chipLabel(chip) {
  return typeof chip === 'string' ? chip : chip.label || chip.query || 'Catalog';
}

function chipQuery(chip) {
  return typeof chip === 'string' ? chip : chip.query || chip.label || '';
}

function catalogCacheKey(query, surface) {
  return `meanly:catalog:${surface}:${query || ''}`;
}

function cachedCatalog(query, surface) {
  try {
    return JSON.parse(window.sessionStorage.getItem(catalogCacheKey(query, surface)) || 'null');
  } catch {
    return null;
  }
}

function cacheCatalog(query, surface, catalog) {
  try {
    window.sessionStorage.setItem(catalogCacheKey(query, surface), JSON.stringify(catalog));
  } catch {
    // Catalog cache is a navigation smoothness optimization only.
  }
}

export function CatalogSurface({ query = '', surface = 'catalog', initialCatalog = null }) {
  const [catalog, setCatalog] = useState(initialCatalog);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setError('');

    if (surface !== 'home') {
      setCatalog(null);
      return undefined;
    }

    if (initialCatalog) {
      cacheCatalog(query, surface, initialCatalog);
      setCatalog(initialCatalog);
      return undefined;
    }

    setCatalog(cachedCatalog(query, surface));

    fetchStorefrontCatalog(query)
      .then((payload) => {
        if (cancelled) return;
        cacheCatalog(query, surface, payload);
        setCatalog(payload);
      })
      .catch((exception) => {
        if (!cancelled) {
          setError(exception.message || 'Catalog is temporarily unavailable.');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [initialCatalog, query, surface]);

  const quickChips = catalog?.quick_chips?.length ? catalog.quick_chips : DEFAULT_QUICK_CHIPS;
  const isHome = surface === 'home';

  return (
    <>
      <section className={isHome ? 'hero hero--search-home' : 'hero hero--catalog-browse'}>
        <h1>{isHome ? 'Meanly' : (query.trim() ? 'Search products.' : 'Browse products.')}</h1>
        <p>
          {isHome
            ? 'Search products, brands, regions, values, and categories. Press Enter to ask Meanly, or choose an exact match from the dropdown.'
            : 'Search products, brands, regions, values, and categories. Choose an exact match or press Enter to ask Meanly.'}
        </p>
        <AskSearchBox initialQuery={query} />
        {error ? <p className="product-card__reason">{error}</p> : null}
        {isHome && quickChips.length > 0 ? (
          <div className="hero-chips" aria-label="Popular searches">
            {quickChips.map((chip) => (
              <Link key={chipQuery(chip)} href={`/?q=${encodeURIComponent(chipQuery(chip))}`}>
                {chipLabel(chip)}
              </Link>
            ))}
          </div>
        ) : null}
      </section>
    </>
  );
}
