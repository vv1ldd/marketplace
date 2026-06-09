'use client';

import { useState } from 'react';
import { ProductCard } from './ProductCard';

const INITIAL_LIMIT = 4;
const STEP = 8;

function numericValue(product) {
  const price = product.selected_offer?.price?.amount;
  const faceValue = product.face_value;
  const parsed = Number(price || faceValue || 0);

  return Number.isFinite(parsed) ? parsed : 0;
}

function productKey(product, index = 0) {
  return `${product.type || 'product'}-${product.id || product.slug || index}`;
}

function uniqueBy(items, keyFn) {
  const seen = new Set();

  return items.filter((item) => {
    const key = keyFn(item);
    if (!key || seen.has(key)) {
      return false;
    }
    seen.add(key);
    return true;
  });
}

function recommendedProduct(product, label) {
  return {
    ...product,
    recommendation_label: label,
  };
}

export function VariantPreviewList({ products = [], total = 0 }) {
  const [visible, setVisible] = useState(Math.min(INITIAL_LIMIT, products.length));
  const byValue = [...products].sort((a, b) => numericValue(b) - numericValue(a));
  const upsells = byValue
    .slice(0, visible)
    .map((product) => recommendedProduct(product, 'Upsell'));
  const upsellKeys = new Set(upsells.map((product, index) => productKey(product, index)));
  const crossSellPool = products.filter((product, index) => !upsellKeys.has(productKey(product, index)));
  const crossSells = uniqueBy(crossSellPool, (product) => [
    product.region || 'global',
    product.face_value_currency || product.selected_offer?.price?.currency || '',
    product.brand || '',
  ].join('|'))
    .slice(0, Math.min(visible, INITIAL_LIMIT))
    .map((product) => recommendedProduct(product, 'Cross-sell'));
  const canLoadMore = visible < products.length;

  return (
    <section className="catalog-section">
      <div className="section-heading">
        <h2>Recommended next choices</h2>
        <p>{total || products.length} products in this family. Start with upsells, then compare nearby alternatives.</p>
      </div>

      {upsells.length > 0 ? (
        <div className="recommendation-block">
          <div className="section-heading section-heading--compact">
            <h3>Upsell options</h3>
            <p>Higher nominal, stronger value, or better checkout-ready option in this product family.</p>
          </div>
          <div className="grid">
            {upsells.map((product, index) => (
              <ProductCard key={productKey(product, index)} product={product} />
            ))}
          </div>
        </div>
      ) : null}

      {crossSells.length > 0 ? (
        <div className="recommendation-block">
          <div className="section-heading section-heading--compact">
            <h3>Cross-sell ideas</h3>
            <p>Nearby regions and adjacent alternatives buyers may compare before checkout.</p>
          </div>
          <div className="grid">
            {crossSells.map((product, index) => (
              <ProductCard key={productKey(product, index)} product={product} />
            ))}
          </div>
        </div>
      ) : null}

      {canLoadMore ? (
        <div className="load-more-row">
          <button type="button" onClick={() => setVisible((count) => Math.min(count + STEP, products.length))}>
            Show more recommendations
          </button>
        </div>
      ) : null}
    </section>
  );
}
