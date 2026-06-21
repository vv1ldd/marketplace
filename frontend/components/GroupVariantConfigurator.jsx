'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { useLocale } from './LocaleProvider';

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

function priceLabel(variant, waitingLabel) {
  const price = variant?.offer?.price || variant?.price;
  if (!price?.amount || !price?.currency) {
    return waitingLabel;
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

function regionMatches(left, right) {
  return String(left || '').trim().toLowerCase() === String(right || '').trim().toLowerCase();
}

export function GroupVariantConfigurator({ group }) {
  const { t } = useLocale();
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
      if (region) {
        return [];
      }

      return (facets.nominals || []).map((option) => ({
        value: option.key || [option.face_value, option.currency].filter(Boolean).join('|'),
        label: option.label || option.value || option.key,
        count: option.count || 0,
      }));
    }

    const scoped = region
      ? selectableVariants.filter((variant) => regionMatches(variant.region, region))
      : selectableVariants;

    return Array.from(groupBy(scoped, (variant) => variant.nominal_key).entries())
      .map(([value, items]) => ({
        value,
        label: items[0]?.nominal_label || items[0]?.nominal_value || value.replace('|', ' '),
        count: items.length,
      }))
      .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }));
  }, [facets.nominals, region, selectableVariants]);

  const selectedVariant = selectableVariants.find((variant) => (
    regionMatches(variant.region, region) && variant.nominal_key === nominalKey
  )) || (info.selection_ready ? variantFromSelectedProduct(info.selected_product) : null);
  const checkoutProductId = selectedVariant?.offer?.product_id;
  const canResolveOffer = region && nominalKey && !selectedVariant;

  return (
    <div className="buyer-product-copy group-product-layout">
      <section className="variant-selector">
        <div className="section-heading section-heading--compact">
          <h2>{t('catalog_show_choose_parameters')}</h2>
        </div>

        <div className="variant-select-form">
          <label>
            {t('catalog_show_country_region')}
            <select
              value={region}
              onChange={(event) => {
                setRegion(event.target.value);
                setNominalKey('');
              }}
            >
              <option value="">{t('catalog_show_select_region')}</option>
              {regions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label>
            {t('catalog_show_nominal')}
            <select
              disabled={!region}
              value={nominalKey}
              onChange={(event) => setNominalKey(event.target.value)}
            >
              <option value="">{region ? t('catalog_show_select_nominal') : t('catalog_show_choose_country_first')}</option>
              {nominals.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
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
            <Link href={groupHref(info, { region, nominal: nominalKey })}>{t('catalog_show_show_offer')}</Link>
          </div>
        ) : null}
      </section>

      <aside className="checkout-panel">
        <div>
          {selectedVariant?.seller?.name ? (
            <div className="seller">{selectedVariant.seller.name}</div>
          ) : null}
          <div className="price">{priceLabel(selectedVariant, t('catalog_show_waiting_price'))}</div>
        </div>
        {selectedVariant ? (
          <>
            <p className="checkout-note">{t('catalog_show_group_checkout_note')}</p>
            <div className="product-card__actions">
              {checkoutProductId ? (
                <Link href={`/checkout?product_id=${checkoutProductId}`}>{t('catalog_show_buy_now')}</Link>
              ) : (
                <span>{t('catalog_show_no_checkout_offer')}</span>
              )}
            </div>
          </>
        ) : canResolveOffer ? (
          <div className="product-card__actions">
            <Link href={groupHref(info, { region, nominal: nominalKey })}>{t('catalog_show_show_matching_offer')}</Link>
          </div>
        ) : (
          <p className="checkout-hint">{t('catalog_show_choose_region_nominal_resolve')}</p>
        )}
      </aside>
    </div>
  );
}
