import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { actionLabel, blockingReasonLabel } from '../../../components/ProductCard';
import { fetchStorefrontCatalog, fetchStorefrontProduct } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

function initials(product) {
  return (product.brand || product.name || 'M')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('');
}

function priceLabel(product) {
  const price = product.selected_offer?.price;

  if (!price?.amount || !price?.currency) {
    return 'Waiting for price';
  }

  return `${price.amount} ${price.currency}`;
}

function queryFromSlug(slug) {
  return String(slug || '')
    .replace(/[-_]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function productCandidates(catalog = {}) {
  return [
    ...(catalog.products?.featured || []),
    ...(catalog.products?.browse || []),
    ...(catalog.products?.provider_network || []),
    ...(catalog.products?.groups || []),
    ...(catalog.product_groups || []),
  ].filter((product) => product?.slug);
}

function canonicalProductMatch(catalog, requestedSlug) {
  const normalized = String(requestedSlug || '').trim();
  if (!normalized) {
    return null;
  }

  return productCandidates(catalog).find((product) => {
    const candidateSlug = String(product.slug || '').trim();

    return candidateSlug === normalized
      || candidateSlug.startsWith(`${normalized}-`)
      || normalized.startsWith(`${candidateSlug}-`);
  }) || null;
}

async function fetchProductOrRedirect(slug) {
  try {
    return await fetchStorefrontProduct(slug);
  } catch (error) {
    if (error.status !== 404) {
      throw error;
    }

    const catalog = await fetchStorefrontCatalog(queryFromSlug(slug));
    const match = canonicalProductMatch(catalog, slug);

    if (match?.slug && match.slug !== slug) {
      redirect(`/products/${match.slug}`);
    }

    notFound();
  }
}

export default async function ProductPage({ params }) {
  const { slug } = await params;
  const product = await fetchProductOrRedirect(slug);
  const actions = product.actions || {};
  const allowedActions = actions.allowed_actions || [];
  const checkoutProductId = product.selected_offer?.product_id;

  return (
    <main className="page">
      <div className="product-layout">
        <div className="product-main-column">
          <section className="product-panel">
            <div className="buyer-product-hero">
              <div className="buyer-product-image" aria-hidden="true">
                {initials(product)}
              </div>
              <div className="buyer-product-summary">
                <p className="eyebrow">{product.category?.label || 'Catalog product'}</p>
                <h1>{product.name}</h1>
                <p>
                  Check the product details first. Sign in only when you are
                  ready to buy, save, or view it in your vault.
                </p>
                <div className="buyer-seller-line">
                  <span>Seller</span>
                  <strong>{product.selected_offer?.seller_name || 'Seller pending'}</strong>
                  <span>{product.region || 'global'}</span>
                </div>
              </div>
            </div>

            <div className="buyer-product-copy">
              <div className="product-card__meta">
                <span>{product.status_label || 'Public catalog'}</span>
                <span>{product.brand || product.product_family || 'Meanly'}</span>
              </div>
              <div className="buyer-trust-list">
                <div className="buyer-trust-item">
                  <strong>Price first</strong>
                  See available pricing before sign-in is needed.
                </div>
                <div className="buyer-trust-item">
                  <strong>Region matters</strong>
                  Availability depends on region, value, and seller.
                </div>
                <div className="buyer-trust-item">
                  <strong>Sign in to buy</strong>
                  Meanly helps keep purchases, receipts, and safe codes together.
                </div>
              </div>
            </div>
          </section>
        </div>

        <aside className="checkout-panel">
          <div>
            <div className="seller">{product.selected_offer?.seller_name || 'Seller pending'}</div>
            <div className="price">{priceLabel(product)}</div>
          </div>
          <p className="checkout-note">
            Sign in when you are ready to buy or save this product.
          </p>
          <div className="product-card__actions">
            {allowedActions.map((action) => (
              <Link
                key={action}
                href={action === 'CHECKOUT' && checkoutProductId ? `/checkout?product_id=${checkoutProductId}` : '/'}
                data-action={action}
              >
                {actionLabel(action)}
              </Link>
            ))}
          </div>
          {actions.blocking_reason ? (
            <p className="product-card__reason">{blockingReasonLabel(actions.blocking_reason)}</p>
          ) : null}
        </aside>
      </div>
    </main>
  );
}
