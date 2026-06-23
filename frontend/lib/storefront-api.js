export const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');
export const storefrontUrl = (process.env.NEXT_PUBLIC_STOREFRONT_URL || 'https://meanly.test').replace(/\/+$/, '');
export const storefrontTokenStorageKey = 'meanly:storefront-token';
export const VAULT_STOREFRONT_SCOPES = [
  'storefront:read',
  'storefront:checkout',
  'storefront:vault',
  'storefront:partner-registration',
];

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

function storefrontBrowserHost(forwardedHost) {
  if (forwardedHost) {
    return String(forwardedHost).split(':')[0].toLowerCase();
  }

  if (typeof window !== 'undefined') {
    return window.location.hostname.toLowerCase();
  }

  try {
    return new URL(storefrontUrl).hostname.toLowerCase();
  } catch {
    return 'meanly.test';
  }
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
    forwardedHost,
  } = options;
  const requestHeaders = {
    Accept: 'application/json',
    'X-Forwarded-Host': storefrontBrowserHost(forwardedHost),
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

let csrfTokenPromise = null;

async function fetchCsrfToken() {
  if (!csrfTokenPromise) {
    csrfTokenPromise = fetch(apiEndpoint('/csrf-token'), {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(payload.message || payload.error || 'Could not prepare protected request.');
        }

        return payload.csrf_token || '';
      })
      .catch((error) => {
        csrfTokenPromise = null;
        throw error;
      });
  }

  return csrfTokenPromise;
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
  const query = {
    return_to: returnTo,
    mode,
    intent_type: intentType,
    intent_title: intentTitle,
    intent_description: intentDescription,
    intent_cta: intentCta,
    intent_nonce: intentNonce,
    intent_resource: intentResource,
  };

  return storefrontPath('/vault/connect', query);
}

function storefrontPath(path, query = {}) {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const params = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  const search = params.toString();

  return search ? `${normalizedPath}?${search}` : normalizedPath;
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
    returnTo: frontendUrl('/merchant/register', { sl1_handoff: 1 }),
    intentType: 'meanly.partner.onboarding',
    intentTitle: 'Start partner onboarding',
    intentDescription: 'Connect identity before Meanly opens seller onboarding.',
    intentCta: 'Open Merchant Center',
  });
}

export function merchantConnectUrl(returnTo = frontendUrl('/merchant')) {
  return simpleL1ConnectUrl({
    returnTo,
    mode: 'login',
    intentType: 'meanly.merchant.open',
    intentTitle: 'Open Merchant Center',
    intentDescription: 'Approve sign-in in Meanly One and continue to Merchant Center.',
    intentCta: 'Open Merchant',
  });
}

export async function fetchStorefrontContext(options = {}) {
  return storefrontFetch('/api/storefront/v1/context', {
    next: { revalidate: 60 },
    ...options,
  });
}

export async function fetchStorefrontCatalog(query = '') {
  const payload = await storefrontFetch('/api/storefront/v1/catalog', {
    query: { q: query },
    next: { revalidate: 60 },
  });

  return payload.data;
}

export async function submitCatalogNeedRequest(formData) {
  const csrfToken = await fetchCsrfToken();
  const response = await fetch(apiEndpoint('/catalog/need-requests'), {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: formData,
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = payload.message || payload.error || `Need request failed: ${response.status}`;
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export async function fetchStorefrontSuggestions(query = '') {
  const payload = await storefrontFetch('/api/storefront/v1/catalog/suggest', {
    query: { q: query },
    cache: 'no-store',
  });

  return payload.data;
}

export async function submitStorefrontChat(message, history = []) {
  const csrfToken = await fetchCsrfToken();
  const response = await fetch(apiEndpoint('/storefront/chat'), {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ message, history }),
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || payload.error || `Meanly AI failed: ${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
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

export async function refreshStorefrontVaultToken(scopes = VAULT_STOREFRONT_SCOPES) {
  const issued = await claimSimpleL1Handoff(scopes);

  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(storefrontTokenStorageKey, issued.access_token);
      window.localStorage.setItem('meanly:vault-preferred-method', 'web');
      document.cookie = 'meanly_vault_access=open; Path=/; Max-Age=2592000; SameSite=Lax';
    } catch {
      // Browser storage is only a convenience; backend session remains authority.
    }
  }

  return issued.access_token;
}

export async function logoutStorefrontSession() {
  const csrfToken = await fetchCsrfToken();
  const postLogout = (token) => fetch(apiEndpoint('/logout'), {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    redirect: 'manual',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': token,
    },
  });

  let response = await postLogout(csrfToken);
  csrfTokenPromise = null;

  if (response.status === 419) {
    response = await postLogout(await fetchCsrfToken());
    csrfTokenPromise = null;
  }

  if (!response.ok && response.status !== 401) {
    const payload = await response.json().catch(() => ({}));
    const error = new Error(payload.message || payload.error || (response.status >= 300 && response.status < 400 ? 'Logout returned a legacy redirect.' : `Logout failed: ${response.status}`));
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return true;
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

export async function fetchPremiumWalletAssets(token) {
  return storefrontFetch('/api/storefront/v1/wallet/assets', {
    token,
    cache: 'no-store',
  });
}

export async function fetchWalletSummary(token) {
  return storefrontFetch('/api/storefront/v1/wallet', {
    token,
    cache: 'no-store',
  });
}

export async function fetchWalletBindings(token) {
  return storefrontFetch('/api/storefront/v1/wallet/bindings', {
    token,
    cache: 'no-store',
  });
}

export async function requestWalletBindingChallenge(token, body) {
  return storefrontFetch('/api/storefront/v1/wallet/bindings/challenge', {
    token,
    body,
    cache: 'no-store',
  });
}

export async function verifyWalletBindingChallenge(token, body) {
  return storefrontFetch('/api/storefront/v1/wallet/bindings/verify', {
    token,
    body,
    cache: 'no-store',
  });
}

export async function provisionManagedWalletBinding(token, bindingKey) {
  return storefrontFetch('/api/storefront/v1/wallet/bindings/managed', {
    token,
    body: { binding_key: bindingKey },
    cache: 'no-store',
  });
}

export async function importManagedWalletBinding(token, body) {
  return storefrontFetch('/api/storefront/v1/wallet/bindings/managed/import', {
    token,
    body,
    cache: 'no-store',
  });
}

export async function revokeWalletBinding(token, bindingId) {
  return storefrontFetch(`/api/storefront/v1/wallet/bindings/${bindingId}`, {
    token,
    method: 'DELETE',
    cache: 'no-store',
  });
}

export async function fetchWalletBundle(token) {
  const core = await fetchWalletCoreBundle(token);

  return enrichWalletBundleAssets(token, core);
}

export function mergeWalletBundle(summary, bindings, assets) {
  return {
    ...(assets || {}),
    identity: summary?.identity || null,
    settlement_networks: summary?.settlement_networks || null,
    vault: summary?.vault || null,
    wallet_summary: summary || null,
    capabilities: summary?.capabilities || null,
    wallet_bindings: bindings?.items || [],
    bindings_contract: bindings?.contract || null,
    bindings_vault_id: bindings?.vault_id || summary?.vault?.id || null,
  };
}

function bindingsPayloadFromWallet(wallet) {
  if (!wallet) {
    return null;
  }

  return {
    items: wallet.wallet_bindings || [],
    vault_id: wallet.bindings_vault_id || wallet.vault?.id || null,
    contract: wallet.bindings_contract || null,
  };
}

/** Fast path: identity, capabilities, and bindings — enough to render the vault dashboard. */
export async function fetchWalletCoreBundle(token) {
  const [summaryResult, bindingsResult] = await Promise.allSettled([
    fetchWalletSummary(token),
    fetchWalletBindings(token),
  ]);

  if (summaryResult.status === 'rejected') {
    throw summaryResult.reason;
  }

  return mergeWalletBundle(
    summaryResult.value,
    bindingsResult.status === 'fulfilled' ? bindingsResult.value : null,
    null,
  );
}

export async function enrichWalletBundleAssets(token, wallet) {
  if (!token || !wallet) {
    return wallet;
  }

  try {
    const assets = await fetchPremiumWalletAssets(token);

    return mergeWalletBundle(
      wallet.wallet_summary || null,
      bindingsPayloadFromWallet(wallet),
      assets,
    );
  } catch {
    return wallet;
  }
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

export async function resolveSettlementRecipient(alias, token) {
  return storefrontFetch('/api/storefront/v1/settlement/resolve-recipient', {
    token,
    body: { alias },
    cache: 'no-store',
  });
}

export async function createSettlementPaymentIntent(payload, token) {
  return storefrontFetch('/api/storefront/v1/settlement/payment-intents', {
    token,
    body: payload,
    cache: 'no-store',
  });
}

export async function executeSettlementPaymentIntent(intentUuid, token) {
  return storefrontFetch(`/api/storefront/v1/settlement/payment-intents/${encodeURIComponent(intentUuid)}/execute`, {
    token,
    body: {},
    cache: 'no-store',
  });
}

export async function listSettlementPaymentIntents(token, query = {}) {
  return storefrontFetch('/api/storefront/v1/settlement/payment-intents', {
    token,
    query,
    cache: 'no-store',
  });
}

export async function submitUsdcTransferProof(payload, token) {
  return storefrontFetch('/api/storefront/v1/wallet/proofs/usdc-transfer', {
    token,
    body: payload,
    cache: 'no-store',
  });
}

export async function listValueEntries(token, query = {}) {
  return storefrontFetch('/api/storefront/v1/wallet/value-entries', {
    token,
    query,
    cache: 'no-store',
  });
}

export async function listBindingEvents(token, query = {}) {
  return storefrontFetch('/api/storefront/v1/wallet/binding-events', {
    token,
    query,
    cache: 'no-store',
  });
}

export async function openPaymentDispute(intentUuid, payload, token) {
  return storefrontFetch(`/api/storefront/v1/settlement/payment-intents/${encodeURIComponent(intentUuid)}/disputes`, {
    token,
    body: payload,
    cache: 'no-store',
  });
}

export async function fetchPaymentIntentDispute(intentUuid, token) {
  return storefrontFetch(`/api/storefront/v1/settlement/payment-intents/${encodeURIComponent(intentUuid)}/dispute`, {
    token,
    cache: 'no-store',
  });
}

export async function fetchIdentityStatement(token, query = {}) {
  return storefrontFetch('/api/storefront/v1/settlement/statement', {
    token,
    query,
    cache: 'no-store',
  });
}

export async function fetchPaymentIntentTimeline(intentUuid, token) {
  return storefrontFetch(`/api/storefront/v1/settlement/payment-intents/${encodeURIComponent(intentUuid)}/timeline`, {
    token,
    cache: 'no-store',
  });
}
