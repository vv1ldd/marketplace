export function buildAuthorizeParams(searchParams) {
  const pick = (...keys) => {
    for (const key of keys) {
      const value = searchParams.get(key);
      if (value) {
        return value;
      }
    }

    return '';
  };

  return {
    clientId: pick('client_id', 'clientId') || 'unknown-client',
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
