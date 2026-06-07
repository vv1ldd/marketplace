'use client';

import { useState } from 'react';
import { ProductCard } from './ProductCard';

const INITIAL_LIMIT = 4;
const STEP = 8;

export function VariantPreviewList({ products = [], total = 0 }) {
  const [visible, setVisible] = useState(Math.min(INITIAL_LIMIT, products.length));
  const shown = products.slice(0, visible);
  const canLoadMore = visible < products.length;

  return (
    <section className="catalog-section">
      <div className="section-heading">
        <h2>Matching variants</h2>
        <p>{total || products.length} matching variants</p>
      </div>
      <div className="grid">
        {shown.map((product) => (
          <ProductCard key={`${product.type}-${product.id || product.slug}`} product={product} />
        ))}
      </div>
      {canLoadMore ? (
        <div className="load-more-row">
          <button type="button" onClick={() => setVisible((count) => Math.min(count + STEP, products.length))}>
            Load more variants
          </button>
        </div>
      ) : null}
    </section>
  );
}
