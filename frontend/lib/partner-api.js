import { apiUrl, backendUrl } from './storefront-api';

let csrfTokenPromise;

function resolveBackendUrl(pathOrUrl, query = {}) {
  if (/^https?:\/\//i.test(pathOrUrl)) {
    const url = new URL(pathOrUrl);
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value));
      }
    });

    if (
      typeof window !== 'undefined'
      && url.origin === window.location.origin
      && !url.pathname.startsWith('/backend/')
    ) {
      return `/backend${url.pathname}${url.search}`;
    }

    return url.toString();
  }

  if (typeof window !== 'undefined') {
    const url = new URL(`/backend${pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`}`, window.location.origin);
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value));
      }
    });

    return `${url.pathname}${url.search}`;
  }

  return backendUrl(pathOrUrl, query);
}

async function parseJson(response) {
  const text = await response.text();
  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text);
  } catch {
    return { __nonJson: true, message: text };
  }
}

export async function fetchPartnerCsrfToken() {
  if (!csrfTokenPromise) {
    const csrfUrl = typeof window !== 'undefined'
      ? '/backend/csrf-token'
      : new URL('/csrf-token', apiUrl);

    csrfTokenPromise = fetch(csrfUrl, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })
      .then(async (response) => {
        const payload = await parseJson(response);
        if (!response.ok) {
          throw new Error(payload.message || payload.error || 'Could not load CSRF token.');
        }

        return payload.csrf_token;
      })
      .catch((error) => {
        csrfTokenPromise = null;
        throw error;
      });
  }

  return csrfTokenPromise;
}

export async function partnerFetch(pathOrUrl, options = {}) {
  const {
    body,
    query,
    method = body === undefined ? 'GET' : 'POST',
    csrf = method !== 'GET',
    headers = {},
  } = options;
  const requestHeaders = {
    Accept: 'application/json',
    ...headers,
  };

  if (body !== undefined) {
    requestHeaders['Content-Type'] = 'application/json';
  }

  if (csrf) {
    requestHeaders['X-CSRF-TOKEN'] = await fetchPartnerCsrfToken();
  }

  const response = await fetch(resolveBackendUrl(pathOrUrl, query), {
    method,
    headers: requestHeaders,
    body: body === undefined ? undefined : JSON.stringify(body),
    credentials: 'include',
  });
  const payload = await parseJson(response);

  if (payload.__nonJson) {
    const message = String(payload.message || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    throw new Error(message ? `Partner API returned non-JSON: ${message.slice(0, 180)}` : 'Partner API returned non-JSON.');
  }

  if (!response.ok) {
    const message = payload.message || payload.error || `Partner API failed: ${response.status}`;
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export function fetchPartnerWorkspace() {
  return partnerFetch('/api/partner/workspace/summary');
}

export function fetchPartnerWorkspaceWithCookie(cookieHeader = '') {
  return fetch(new URL('/api/partner/workspace/summary', apiUrl), {
    headers: {
      Accept: 'application/json',
      Cookie: cookieHeader,
    },
    cache: 'no-store',
  }).then(async (response) => {
    const payload = await parseJson(response);
    if (!response.ok) {
      return null;
    }

    return payload;
  }).catch(() => null);
}

export function fetchPartnerModule(endpoint, query = {}) {
  return partnerFetch(endpoint, { query });
}

export function postPartnerAction(endpoint, body = {}) {
  return partnerFetch(endpoint, { body, method: 'POST' });
}
