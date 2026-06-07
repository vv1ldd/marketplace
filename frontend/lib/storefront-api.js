export const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');
export const storefrontUrl = (process.env.NEXT_PUBLIC_STOREFRONT_URL || 'https://meanly.test').replace(/\/+$/, '');
export const storefrontTokenStorageKey = 'meanly:storefront-token';

function apiEndpoint(path, query = {}) {
  if (typeof window !== 'undefined') {
    const url = new URL(`/backend${path.startsWith('/') ? path : `/${path}`}`, window.location.origin);
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value));
      }
    });

    return `${url.pathname}${url.search}`;
  }

  const url = new URL(path, apiUrl);
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return url;
}

async function storefrontFetch(path, options = {}) {
  const {
    token,
    body,
    query,
    next,
    cache,
    method = body === undefined ? 'GET' : 'POST',
    headers = {},
    credentials,
  } = options;
  const requestHeaders = {
    Accept: 'application/json',
    ...headers,
  };

  if (body !== undefined) {
    requestHeaders['Content-Type'] = 'application/json';
  }
  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(apiEndpoint(path, query), {
    method,
    headers: requestHeaders,
    body: body === undefined ? undefined : JSON.stringify(body),
    next,
    cache,
    credentials,
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = payload.message || payload.error || `Storefront API failed: ${response.status}`;
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export function backendUrl(path, query = {}) {
  return apiEndpoint(path, query).toString();
}

export function frontendUrl(path, query = {}) {
  const url = new URL(path, storefrontUrl);
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
}

export function vaultHandoffUrl(query = {}) {
  return frontendUrl('/vault', {
    sl1_handoff: 1,
    ...query,
  });
}

export function simpleL1ConnectUrl({
  returnTo = vaultHandoffUrl(),
  mode = 'connect',
  intentType = 'meanly.vault.open',
  intentTitle = 'Open customer vault',
  intentDescription,
  intentCta = 'Continue with Meanly',
  intentNonce,
  intentResource,
} = {}) {
  return frontendUrl('/simple-l1/connect', {
    return_to: returnTo,
    mode,
    intent_type: intentType,
    intent_title: intentTitle,
    intent_description: intentDescription,
    intent_cta: intentCta,
    intent_nonce: intentNonce,
    intent_resource: intentResource,
  });
}

export function opsConnectUrl() {
  return simpleL1ConnectUrl({
    returnTo: frontendUrl('/ops'),
    intentType: 'meanly.ops.open',
    intentTitle: 'Open Meanly Ops',
    intentDescription: 'Connect identity before Meanly opens operations tools.',
    intentCta: 'Open Ops',
  });
}

export function partnerConnectUrl() {
  return simpleL1ConnectUrl({
    returnTo: frontendUrl('/partner/register', { sl1_handoff: 1 }),
    intentType: 'meanly.partner.onboarding',
    intentTitle: 'Start partner onboarding',
    intentDescription: 'Connect identity before Meanly opens seller onboarding.',
    intentCta: 'Open Merchant Center',
  });
}

export async function fetchStorefrontContext() {
  return storefrontFetch('/api/storefront/v1/context', {
    next: { revalidate: 60 },
  });
}

export async function fetchStorefrontCatalog(query = '') {
  const payload = await storefrontFetch('/api/storefront/v1/catalog', {
    query: { q: query },
    next: { revalidate: 60 },
  });

  return payload.data;
}

export async function fetchStorefrontSuggestions(query = '') {
  const payload = await storefrontFetch('/api/storefront/v1/catalog/suggest', {
    query: { q: query },
    cache: 'no-store',
  });

  return payload.data;
}

export async function fetchStorefrontProduct(slug) {
  const payload = await storefrontFetch(`/api/storefront/v1/catalog/products/${slug}`, {
    next: { revalidate: 60 },
  });

  return payload.data;
}

export async function fetchStorefrontCategory(category, query = {}) {
  const payload = await storefrontFetch(`/api/storefront/v1/catalog/categories/${category}`, {
    query,
    next: { revalidate: 60 },
  });

  return payload.data;
}

export async function fetchStorefrontGroup(category, brandSlug, kindSlug, query = {}) {
  return storefrontFetch(`/api/storefront/v1/catalog/groups/${category}/${brandSlug}/${kindSlug}`, {
    query: { compact: 1, ...query },
    next: { revalidate: 60 },
  });
}

export async function fetchOrderSafeStatus(orderUuid, token) {
  const payload = await storefrontFetch(`/api/storefront/v1/orders/${orderUuid}/safe/status`, {
    token,
  });

  return payload.decision;
}

export async function openOrderSafe(orderUuid, token) {
  return storefrontFetch(`/api/storefront/v1/orders/${orderUuid}/safe/open`, {
    token,
    body: {},
  });
}

export async function scratchOrderSafe(orderUuid, token, scratchProof) {
  return storefrontFetch(`/api/storefront/v1/orders/${orderUuid}/safe/scratch`, {
    token,
    body: { scratch_proof: scratchProof },
  });
}

export async function fetchOrderSafeSupport(orderUuid, token) {
  return storefrontFetch(`/api/storefront/v1/orders/${orderUuid}/safe/support`, {
    token,
  });
}

export async function fetchCheckoutIntent(payload) {
  return storefrontFetch('/api/storefront/v1/checkout/intent', {
    body: payload,
  });
}

export async function createCheckout(payload, token) {
  const data = await storefrontFetch('/api/storefront/v1/checkout/create', {
    token,
    body: payload,
  });

  return data.order;
}

export async function exchangeSimpleL1ProofToken(proofToken, scopes = ['storefront:read']) {
  return storefrontFetch('/api/storefront/v1/identity/token', {
    body: {
      proof_token: proofToken,
      scopes,
    },
  });
}

export async function claimSimpleL1Handoff(scopes = ['storefront:read']) {
  return storefrontFetch('/api/storefront/v1/identity/handoff', {
    body: { scopes },
    credentials: 'include',
  });
}

export async function fetchStorefrontSession(token) {
  return storefrontFetch('/api/storefront/v1/identity/session', {
    token,
  });
}

export async function fetchVault(token) {
  return storefrontFetch('/api/storefront/v1/vault', {
    token,
  });
}

export async function fetchPersonalizedHome(token) {
  return storefrontFetch('/api/storefront/v1/personalization/home', {
    token,
  });
}

export async function toggleFavorite(product, token) {
  return storefrontFetch('/api/storefront/v1/favorites/toggle', {
    token,
    body: {
      product_slug: product.slug,
      product_name: product.name,
      category_slug: product.category?.slug,
      category_label: product.category?.label,
    },
  });
}

export async function fetchPartnerRegistrationState(token) {
  return storefrontFetch('/api/storefront/v1/partner-registration/state', {
    token,
    cache: 'no-store',
  });
}

export async function fetchUiProjection(surface, path = '', query = {}) {
  const normalizedPath = Array.isArray(path) ? path.join('/') : path;
  const suffix = normalizedPath ? `/${normalizedPath}` : '';

  return storefrontFetch(`/api/ui/v1/projections/${surface}${suffix}`, {
    query,
    cache: 'no-store',
  });
}
