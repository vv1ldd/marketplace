'use client';

import { GroupVariantConfigurator } from './GroupVariantConfigurator';
import { VariantPreviewList } from './VariantPreviewList';
import { useLocale } from './LocaleProvider';

export function CatalogGroupPanel({ group }) {
  const { t } = useLocale();
  const info = group.group || {};
  const products = group.products || [];

  return (
    <main className="page">
      <section className="product-panel">
        <div className="buyer-product-hero">
          <div className="buyer-product-image" aria-hidden="true">
            {(info.brand || 'M').slice(0, 2)}
          </div>
          <div className="buyer-product-summary">
            <h1>{info.title || t('catalog_show_product_group_fallback')}</h1>
            {info.description || group.meta?.description || group.meta?.description_ru ? (
              <p>{info.description || group.meta?.description || group.meta?.description_ru}</p>
            ) : null}
          </div>
        </div>

        <GroupVariantConfigurator group={group} />
      </section>

      <VariantPreviewList products={products} total={group.pagination?.total} />
    </main>
  );
}
