const INTENT_CORRIDORS = new Set([
  'play',
  'stream',
  'work',
  'shop',
  'pay',
  'mobile',
  'go',
  'unclassified',
]);

const LEGACY_CATEGORY_TO_INTENT = {
  console_payment_cards: 'play',
  game_wallet_topups: 'play',
  subscriptions: 'stream',
  software_licenses: 'work',
  gift_cards: 'shop',
  payment_prepaid_cards: 'pay',
  mobile_app_store_cards: 'mobile',
  telecom_topups: 'mobile',
  travel_entertainment_vouchers: 'go',
};

export function resolveGroupIntent(segment = '') {
  const value = String(segment || '').trim().toLowerCase();

  if (INTENT_CORRIDORS.has(value)) {
    return value;
  }

  return LEGACY_CATEGORY_TO_INTENT[value] || 'shop';
}

export function groupProductSlug(brandSlug, kindSlug) {
  return `${String(brandSlug || '').trim()}-${String(kindSlug || '').trim()}`;
}

export function productPath(slug, query = {}) {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  const path = `/products/${slug}`;

  return `${path}${params.toString() ? `?${params.toString()}` : ''}`;
}

export function groupCatalogPath(intent, brandSlug, kindSlug, query = {}) {
  return productPath(groupProductSlug(brandSlug, kindSlug), query);
}

export function groupCatalogPathFromInfo(info = {}, query = {}) {
  const brandSlug = info.brand_slug || info.brandSlug;
  const kind = info.kind || info.kind_slug || info.kindSlug;
  const slug = info.slug || (brandSlug && kind ? groupProductSlug(brandSlug, kind) : null);

  if (!slug) {
    return null;
  }

  return productPath(slug, query);
}

export function isGroupCatalogPath(pathname = '') {
  return pathname.startsWith('/products/')
    || pathname.startsWith('/g/')
    || pathname.startsWith('/catalog/groups/');
}
