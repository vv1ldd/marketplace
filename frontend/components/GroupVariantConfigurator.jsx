'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';

function groupBy(items, keyFn) {
  return items.reduce((carry, item) => {
    const key = keyFn(item);
    if (!key) {
      return carry;
    }

    carry.set(key, [...(carry.get(key) || []), item]);
    return carry;
  }, new Map());
}

function priceLabel(variant) {
  const price = variant?.offer?.price || variant?.price;
  if (!price?.amount || !price?.currency) {
    return 'Waiting for price';
  }

  return `${price.amount} ${price.currency}`;
}

function groupHref(info, query = {}) {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  const path = `/catalog/groups/${info.category}/${info.brand_slug}/${info.kind}`;
  return `${path}${params.toString() ? `?${params.toString()}` : ''}`;
}

function variantFromSelectedProduct(product) {
  if (!product) {
    return null;
  }

  const offer = product.selected_offer || null;
  const currency = product.face_value_currency || '';
  const faceValue = product.face_value || '';

  return {
    name: product.name,
    region: product.region,
    region_label: product.region ? String(product.region).toUpperCase() : '',
    nominal_key: faceValue ? `${faceValue}|${currency}` : '',
    nominal_label: [faceValue, currency].filter(Boolean).join(' '),
    offer: offer ? {
      product_id: offer.product_id,
      price: offer.price,
    } : null,
    price: offer?.price || null,
    seller: offer?.seller || null,
  };
}

export function GroupVariantConfigurator({ group }) {
  const info = group.group || {};
  const facets = group.facets || {};
  const selected = facets.selected || {};
  const variants = info.variants || [];
  const availableVariants = variants.filter((variant) => variant.offer?.product_id);
  const selectableVariants = availableVariants.length > 0 ? availableVariants : variants;
  const [region, setRegion] = useState(selected.region || '');
  const [nominalKey, setNominalKey] = useState(selected.nominal_key || '');

  const regions = useMemo(() => {
    if (selectableVariants.length === 0) {
      return (facets.regions || []).map((option) => ({
        value: option.value || option.name,
        label: option.label || option.value || option.name,
        count: option.count || 0,
      }));
    }

    return Array.from(groupBy(selectableVariants, (variant) => variant.region).entries())
      .map(([value, items]) => ({
        value,
        label: items[0]?.region_label || value,
        count: items.length,
      }))
      .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));
  }, [facets.regions, selectableVariants]);

  const nominals = useMemo(() => {
    if (selectableVariants.length === 0) {
      return (facets.nominals || []).map((option) => ({
        value: option.key || [option.face_value, option.currency].filter(Boolean).join('|'),
        label: option.label || option.value || option.key,
        count: option.count || 0,
      }));
    }

    const scoped = region ? selectableVariants.filter((variant) => variant.region === region) : selectableVariants;

    return Array.from(groupBy(scoped, (variant) => variant.nominal_key).entries())
      .map(([value, items]) => ({
        value,
        label: items[0]?.nominal_label || items[0]?.nominal_value || value.replace('|', ' '),
        count: items.length,
      }))
      .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }));
  }, [facets.nominals, region, selectableVariants]);

  const selectedVariant = selectableVariants.find((variant) => (
    variant.region === region && variant.nominal_key === nominalKey
  )) || (info.selection_ready ? variantFromSelectedProduct(info.selected_product) : null);
  const checkoutProductId = selectedVariant?.offer?.product_id;
  const canResolveOffer = region && nominalKey && !selectedVariant;

  return (
    <div className="buyer-product-copy group-product-layout">
      <section className="variant-selector">
        <div className="section-heading">
          <h2>Choose parameters</h2>
          <p>Pick region and nominal to resolve an offer</p>
        </div>

        <div className="variant-select-form">
          <label>
            Region
            <select
              value={region}
              onChange={(event) => {
                setRegion(event.target.value);
                setNominalKey('');
              }}
            >
              <option value="">Select region</option>
              {regions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label} ({option.count})
                </option>
              ))}
            </select>
          </label>

          <label>
            Nominal
            <select
              disabled={!region}
              value={nominalKey}
              onChange={(event) => setNominalKey(event.target.value)}
            >
              <option value="">{region ? 'Select nominal' : 'Select region first'}</option>
              {nominals.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label} ({option.count})
                </option>
              ))}
            </select>
          </label>
        </div>

        {selectedVariant ? (
          <div className="selected-variant-card">
            <strong>{selectedVariant.name}</strong>
            <span>{selectedVariant.region_label || selectedVariant.region} · {selectedVariant.nominal_label}</span>
          </div>
        ) : canResolveOffer ? (
          <div className="product-card__actions">
            <Link href={groupHref(info, { region, nominal: nominalKey })}>Show offer</Link>
          </div>
        ) : null}
      </section>

      <aside className="checkout-panel">
        <div>
          <div className="seller">{selectedVariant?.seller?.name || 'Seller pending'}</div>
          <div className="price">{priceLabel(selectedVariant)}</div>
        </div>
        <p className="checkout-note">
          Select region and nominal. Checkout availability is confirmed when you continue.
        </p>
        {selectedVariant ? (
          <div className="product-card__actions">
            {checkoutProductId ? (
              <Link href={`/checkout?product_id=${checkoutProductId}`}>Buy now</Link>
            ) : (
              <span>No checkout offer</span>
            )}
          </div>
        ) : canResolveOffer ? (
          <div className="product-card__actions">
            <Link href={groupHref(info, { region, nominal: nominalKey })}>Show matching offer</Link>
          </div>
        ) : (
          <p className="product-card__reason">Choose region and nominal to resolve a specific product.</p>
        )}
      </aside>
    </div>
  );
}
