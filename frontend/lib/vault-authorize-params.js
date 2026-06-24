function resolveAuthorizeSearchParams(searchParams) {
  if (typeof window !== 'undefined' && window.location.search) {
    return new URLSearchParams(window.location.search);
  }

  if (searchParams && typeof searchParams.get === 'function') {
    return searchParams;
  }

  return new URLSearchParams();
}

export function buildAuthorizeParams(searchParams) {
  const paramsSource = resolveAuthorizeSearchParams(searchParams);

  const pick = (...keys) => {
    for (const key of keys) {
      const value = paramsSource.get(key);
      if (value) {
        return value;
      }
    }

    return '';
  };

  return {
    clientId: pick('client_id', 'clientId')
      || (typeof window !== 'undefined' ? window.location.hostname.replace(/^www\./, '') : 'unknown-client'),
    clientName: pick('client_name', 'clientName'),
    uiTheme: pick('ui_theme', 'uiTheme'),
    redirectUri: pick('redirect_uri', 'redirectUri'),
    scope: pick('scope') || 'openid sl1e',
    state: pick('state'),
    nonce: pick('nonce'),
    mode: pick('mode') === 'register' ? 'register' : 'login',
    responseMode: pick('response_mode', 'responseMode') || 'query',
    intentType: pick('intent_type', 'intentType'),
    intentTitle: pick('intent_title', 'intentTitle'),
    intentDescription: pick('intent_description', 'intentDescription'),
    intentCta: pick('intent_cta', 'intentCta'),
    intentNonce: pick('intent_nonce', 'intentNonce'),
    intentResource: pick('intent_resource', 'intentResource'),
    handoffId: pick('handoff_id', 'handoffId'),
    handoffToken: pick('handoff_token', 'handoffToken'),
    requestHost: typeof window !== 'undefined' ? window.location.hostname : '',
  };
}

export function buildAuthorizeParamsFromPath(path, origin = typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test') {
  const url = new URL(String(path), origin);
  return buildAuthorizeParams(url.searchParams);
}

export function buildAuthorizeParamsFromRedirect(redirectUrl, origin = typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test') {
  const url = new URL(String(redirectUrl), origin);
  return buildAuthorizeParams(url.searchParams);
}

export function buildSl1eAuthorizePayload(extra = {}, searchParams = null) {
  const oauth = buildAuthorizeParams(searchParams);

  return {
    ...oauth,
    client_id: oauth.clientId,
    client_name: oauth.clientName,
    redirect_uri: oauth.redirectUri,
    request_host: oauth.requestHost,
    ...extra,
    clientId: extra.clientId ?? oauth.clientId,
    clientName: extra.clientName ?? oauth.clientName,
    redirectUri: extra.redirectUri ?? oauth.redirectUri,
    requestHost: extra.requestHost ?? oauth.requestHost,
  };
}
