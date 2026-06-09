import Link from 'next/link';
import { FavoriteButton } from './FavoriteButton';

export function actionLabel(action) {
  return action
    .toLowerCase()
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function blockingReasonLabel(reason) {
  return {
    no_selected_offer: 'No checkout offer yet',
    unavailable: 'Not available for checkout yet',
  }[reason] || reason;
}

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
    return null;
  }

  return `${price.amount} ${price.currency}`;
}

export function ProductCard({ product }) {
  const actions = product.actions || {};
  const allowedActions = actions.allowed_actions || [];
  const href = product.slug ? `/products/${product.slug}` : '#';
  const price = priceLabel(product);
  const recommendationLabel = product.recommendation_label || product.recommendationLabel;

  return (
    <article className="product-card">
      <Link className="product-card__media" href={href} aria-label={`Open ${product.name}`}>
        <span className="product-card__badge">{initials(product)}</span>
      </Link>
      <FavoriteButton product={product} />
      <div className="product-card__body">
        <div className="product-card__meta">
          {recommendationLabel ? <span>{recommendationLabel}</span> : null}
          <span>{product.category?.label || 'Catalog'}</span>
          <span>{product.region || 'global'}</span>
        </div>
        <h3>{product.name}</h3>
        {price ? (
          <p className="product-card__price">{price}</p>
        ) : (
          <p className="product-card__muted">Checkout offer pending</p>
        )}
        <div className="product-card__actions">
          {allowedActions.map((action) => (
            <Link key={action} href={href} data-action={action}>
              {actionLabel(action)}
            </Link>
          ))}
        </div>
        {actions.blocking_reason ? (
          <p className="product-card__reason">{blockingReasonLabel(actions.blocking_reason)}</p>
        ) : null}
      </div>
    </article>
  );
}
