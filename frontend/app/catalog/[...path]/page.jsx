import Link from 'next/link';
import { ProjectionSurface } from '../../../components/ProjectionSurface';
import { GroupVariantConfigurator } from '../../../components/GroupVariantConfigurator';
import { ProductCard } from '../../../components/ProductCard';
import { VariantPreviewList } from '../../../components/VariantPreviewList';
import { fetchStorefrontCategory, fetchStorefrontGroup } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

function queryObject(searchParams = {}) {
  return Object.fromEntries(
    Object.entries(searchParams || {}).filter(([, value]) => value !== undefined && value !== null && value !== ''),
  );
}

function hrefWithQuery(pathname, query = {}) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  return `${pathname}${params.toString() ? `?${params.toString()}` : ''}`;
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

function nominalParam(value = '') {
  const [faceValue = '', currency = ''] = String(value).split('|');

  return { face_value: faceValue, currency };
}

function normalizeGroupQuery(query = {}) {
  if (!query.nominal) {
    return query;
  }

  const { face_value: faceValue, currency } = nominalParam(query.nominal);
  const { nominal, ...rest } = query;

  return {
    ...rest,
    face_value: faceValue,
    currency,
  };
}

function CategoryGroupCard({ group }) {
  const variantGroup = group.variant_group || {};
  const href = localHref(group.links?.self, group.slug ? `/products/${group.slug}` : '/');
  const meta = [
    `${variantGroup.variant_count || 1} variants`,
    variantGroup.region_count ? `${variantGroup.region_count} regions` : null,
    variantGroup.nominal_count ? `${variantGroup.nominal_count} nominals` : null,
  ].filter(Boolean).join(' · ');

  return (
    <Link className="category-card" href={href}>
      <strong>{group.name}</strong>
      <span>{meta}</span>
    </Link>
  );
}

function CatalogResults({ title, description, products = [], pagination }) {
  const grouped = products.some((product) => product.variant_group?.is_grouped);

  return (
    <main className="page">
      <section className="hero">
        <h1>{title}</h1>
        {description ? <p>{description}</p> : null}
      </section>

      <section className="catalog-section">
        <div className="section-heading">
          <h2>{grouped ? 'Choose a group' : 'Products'}</h2>
          <p>{pagination?.total || products.length} {grouped ? 'groups' : 'matches'}</p>
        </div>
        {grouped ? (
          <div className="category-grid">
            {products.map((group) => (
              <CategoryGroupCard key={`${group.type}-${group.id || group.slug}`} group={group} />
            ))}
          </div>
        ) : (
          <div className="grid">
            {products.map((product) => (
              <ProductCard key={`${product.type}-${product.id || product.slug}`} product={product} />
            ))}
          </div>
        )}
      </section>
    </main>
  );
}

function GroupSelector({ group }) {
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
            <p className="eyebrow">Product group</p>
            <h1>{info.title || 'Product group'}</h1>
            <p>{info.description || group.meta?.description_ru}</p>
            <div className="buyer-seller-line">
              <span>{info.category_label}</span>
              <strong>{info.price_range?.label || `${info.variant_count || products.length} variants`}</strong>
              <span>{info.region_count || 0} regions</span>
              <span>{info.nominal_count || 0} nominals</span>
            </div>
          </div>
        </div>

        <GroupVariantConfigurator group={group} />
      </section>

      <VariantPreviewList products={products} total={group.pagination?.total} />
    </main>
  );
}

export async function generateMetadata({ params, searchParams }) {
  const { path = [] } = await params;
  const query = queryObject(await searchParams);

  if (path[0] === 'groups' && path.length >= 4) {
    const brand = path[2]
      .split('-')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
    const kind = path[3].replace(/-/g, ' ');

    return {
      title: `${brand} ${kind} | Meanly`,
      description: 'Choose region and nominal for this product group.',
      alternates: {
        canonical: `/catalog/groups/${path[1]}/${path[2]}/${path[3]}`,
      },
    };
  }

  if (path.length === 1) {
    const category = await fetchStorefrontCategory(path[0], query);
    const surface = category.surface || {};

    return {
      title: `${surface.title || path[0]} | Meanly catalog`,
      description: surface.description || 'Meanly catalog category.',
      alternates: {
        canonical: `/catalog/${path[0]}`,
      },
    };
  }

  return {
    title: 'Meanly catalog',
  };
}

export default async function CatalogProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  const query = queryObject(await searchParams);

  if (path[0] === 'groups' && path.length >= 4) {
    const group = await fetchStorefrontGroup(path[1], path[2], path[3], normalizeGroupQuery(query));

    return <GroupSelector group={group} />;
  }

  if (path.length === 1) {
    const category = await fetchStorefrontCategory(path[0], query);
    const surface = category.surface || {};

    return (
      <CatalogResults
        title={surface.title || path[0]}
        description={surface.description}
        products={category.products?.browse || []}
        pagination={category.pagination}
      />
    );
  }

  return (
    <main className="page">
      <ProjectionSurface surface="catalog" path={path} searchParams={query} />
    </main>
  );
}
