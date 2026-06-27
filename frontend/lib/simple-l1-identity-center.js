import { storefrontRequestHeaders } from './storefront-api';

const CONNECT_TIMEOUT_MS = 12000;

export function backendConnectPath(connectQuery = {}) {
  const params = new URLSearchParams();

  Object.entries(connectQuery).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  params.set('popup', '1');

  return `/simple-l1/connect?${params.toString()}`;
}

export function authorizePathFromRedirect(redirectUrl, origin = typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test') {
  const url = new URL(String(redirectUrl), origin);

  return `/authorize?${url.searchParams.toString()}`;
}

async function fetchWithTimeout(url, options = {}, timeoutMs = CONNECT_TIMEOUT_MS) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal,
    });
  } finally {
    window.clearTimeout(timeout);
  }
}

export async function resolveSimpleL1ConnectHandoff(connectQuery = {}) {
  const response = await fetchWithTimeout(backendConnectPath(connectQuery), {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...storefrontRequestHeaders(),
    },
    cache: 'no-store',
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload.message || payload.error || 'Vault handoff failed.');
  }

  if (!payload.redirect_url) {
    throw new Error('Vault handoff failed.');
  }

  // ADR-0030: when the backend returns a canonical ceremony origin short link
  // (connect.identity.<contour>), the browser must navigate to it as-is. Only
  // the legacy inline flow is rewritten to a local /authorize route.
  const external = payload.external_redirect === true;

  return {
    externalUrl: external ? payload.redirect_url : null,
    authorizePath: external ? null : authorizePathFromRedirect(payload.redirect_url),
    showHandoff: payload.show_handoff === true && Boolean(payload.handoff),
    handoff: payload.handoff || null,
  };
}
