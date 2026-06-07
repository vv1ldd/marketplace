import { apiUrl } from './storefront-api';

let csrfTokenPromise;

async function parseJson(response) {
  const text = await response.text();
  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

function backendPath(path) {
  if (/^https?:\/\//i.test(path)) {
    const url = new URL(path);
    return `/backend${url.pathname}${url.search}${url.hash}`;
  }

  return `/backend${path.startsWith('/') ? path : `/${path}`}`;
}

async function fetchOpsCsrfToken() {
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

async function opsFetch(path, { method = 'GET', body, query, csrf = method !== 'GET' } = {}) {
  const url = new URL(backendPath(path), window.location.origin);
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  const headers = {
    Accept: 'application/json',
  };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  async function requestWithCurrentToken() {
    const requestHeaders = { ...headers };
    if (csrf) {
      requestHeaders['X-CSRF-TOKEN'] = await fetchOpsCsrfToken();
    }

    const response = await fetch(url, {
      method,
      credentials: 'include',
      headers: requestHeaders,
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    return {
      response,
      payload: await parseJson(response),
    };
  }

  let { response, payload } = await requestWithCurrentToken();
  if (!response.ok && response.status === 419 && csrf) {
    csrfTokenPromise = null;
    ({ response, payload } = await requestWithCurrentToken());
  }

  if (!response.ok) {
    const error = new Error(payload.message || payload.error || `Ops request failed: ${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export function fetchOpsPartners({ status = '', search = '', page = 1 } = {}) {
  return opsFetch('/ops/dashboard/partners/data', {
    query: {
      status,
      search,
      page,
    },
  });
}

export function fetchOpsTreasury() {
  return opsFetch('/ops/dashboard/treasury/data');
}

export function fetchOpsLiquidity() {
  return opsFetch('/ops/dashboard/liquidity/data');
}

export function fetchOpsChannels() {
  return opsFetch('/ops/dashboard/channels/data');
}

export function fetchOpsGrowth() {
  return opsFetch('/ops/dashboard/growth/data');
}

export function fetchOpsSearchIntegrations() {
  return opsFetch('/ops/dashboard/search-integrations/data');
}

export function fetchOpsShops({ search = '', page = 1 } = {}) {
  return opsFetch('/ops/dashboard/shops/data', {
    query: { search, page },
  });
}

export function fetchOpsOrders({ status = '', search = '', page = 1 } = {}) {
  return opsFetch('/ops/dashboard/orders/data', {
    query: { status, search, page },
  });
}

export function fetchOpsOperations({ search = '' } = {}) {
  return opsFetch('/ops/dashboard/operations/data', {
    query: { search },
  });
}

export function fetchOpsCatalog({ search = '', page = 1 } = {}) {
  return opsFetch('/ops/dashboard/catalog/data', {
    query: { search, page },
  });
}

export function fetchOpsInventory() {
  return opsFetch('/ops/dashboard/inventory/data');
}

export function syncOpsInventoryWarehouses() {
  return opsFetch('/ops/dashboard/inventory/sync-warehouses', {
    method: 'POST',
  });
}

export function fetchOpsTickets({ search = '', page = 1 } = {}) {
  return opsFetch('/ops/dashboard/tickets/data', {
    query: { search, page },
  });
}

export function fetchOpsTicketDetails(id) {
  return opsFetch(`/ops/dashboard/tickets/${id}/details`);
}

export function replyOpsTicket(id, message) {
  return opsFetch(`/ops/dashboard/tickets/${id}/reply`, {
    method: 'POST',
    body: { message },
  });
}

export function approveOpsPartner(url) {
  return opsFetch(url, {
    method: 'POST',
  });
}

export function topUpOpsPartner(url, { amount, reference }) {
  return opsFetch(url, {
    method: 'POST',
    body: { amount, reference },
  });
}

export function fetchOpsProviders() {
  return opsFetch('/ops/dashboard/providers/data');
}

export function syncOpsProvider(url, mode) {
  return opsFetch(url, {
    method: 'POST',
    body: { mode },
  });
}

export function connectOpsZeroLayer(payload) {
  return opsFetch('/ops/dashboard/zero-layer/connect', {
    method: 'POST',
    body: payload,
  });
}

export function syncOpsZeroLayer(url) {
  return opsFetch(url, {
    method: 'POST',
  });
}

export function runOpsSearchSignalAction(url, payload = {}) {
  return opsFetch(url, {
    method: 'POST',
    body: payload,
  });
}

export function decideOpsRecommendation(id, decision) {
  return opsFetch(`/ops/decision-console/recommendations/${id}/${decision}`, {
    method: 'POST',
  });
}

export function runOpsAiAudit() {
  return opsFetch('/ops/dashboard/ai/audit', {
    method: 'POST',
  });
}

export function sendOpsAiMessage(message) {
  return opsFetch('/ops/dashboard/ai/chat', {
    method: 'POST',
    body: { message },
  });
}

export function validateOpsTribunalChain() {
  return opsFetch('/ops/dashboard/tribunal/validate-chain', {
    method: 'POST',
  });
}

export function traceOpsSimpleLayer1(reference) {
  return opsFetch('/ops/dashboard/simple-layer-1/trace', {
    query: { reference },
  });
}

export function updateOpsTheme(theme) {
  return opsFetch('/ops/dashboard/theme', {
    method: 'POST',
    body: { theme },
  });
}
