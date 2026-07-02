export function isSimpleL1ConnectHref(href) {
  if (!href) {
    return false;
  }

  try {
    const url = new URL(href, typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test');
    const host = url.hostname;
    const allowedHost = typeof window !== 'undefined'
      && (
        host === window.location.hostname
        || host === 'meanly.test'
        || host === 'api.meanly.test'
        || host === 'maestrooo.test'
        || host === 'localhost'
        || host.endsWith('.meanly.test')
        || host.endsWith('.maestrooo.test')
      );

    return allowedHost && (
      url.pathname === '/vault/connect'
      || url.pathname === '/simple-l1/connect'
    );
  } catch {
    return false;
  }
}

export const CONNECT_SIGNAL_TYPE = 'simple-l1-connect-signal';

export function normalizeSimpleL1ConnectHref(href) {
  const url = new URL(href, typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test');

  if (url.pathname === '/simple-l1/connect') {
    url.pathname = '/vault/connect';
    url.searchParams.delete('popup');
  }

  return `${url.pathname}${url.search}${url.hash}`;
}

export function buildConnectLaunchUrl(searchParams = {}) {
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://meanly.test';
  const connectUrl = new URL('/simple-l1/connect', origin);

  Object.entries(searchParams).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      connectUrl.searchParams.set(key, String(value));
    }
  });

  let returnTo = connectUrl.searchParams.get('return_to');
  if (returnTo) {
    const separator = returnTo.includes('?') ? '&' : '?';
    if (!returnTo.includes('sl1_handoff=')) {
      returnTo += `${separator}sl1_handoff=1`;
      connectUrl.searchParams.set('return_to', returnTo);
    }
  }

  connectUrl.searchParams.set('popup', '1');

  return `${connectUrl.pathname}${connectUrl.search}${connectUrl.hash}`;
}

export function parseSimpleL1ConnectSignal(event, expectedOrigin) {
  if (!event || typeof event !== 'object') {
    return null;
  }

  if (expectedOrigin && event.origin !== expectedOrigin) {
    return null;
  }

  const payload = event.data;
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  if (payload.type !== CONNECT_SIGNAL_TYPE) {
    return null;
  }

  const phase = String(payload.phase || '').toUpperCase();
  if (!['READY', 'PROGRESS', 'COMPLETE'].includes(phase)) {
    return null;
  }

  return {
    phase,
    redirectUrl: typeof payload.redirectUrl === 'string' ? payload.redirectUrl : '',
    returnTo: typeof payload.returnTo === 'string' ? payload.returnTo : '',
  };
}

export function handleSimpleL1ConnectClick(event, href, onSignal = null) {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return false;
  }

  if (!isSimpleL1ConnectHref(href)) {
    return false;
  }

  event.preventDefault();
  const normalized = normalizeSimpleL1ConnectHref(href);
  const source = new URL(normalized, window.location.origin);
  const launchPath = buildConnectLaunchUrl({
    return_to: source.searchParams.get('return_to') || '',
    mode: source.searchParams.get('mode') || 'connect',
    intent_type: source.searchParams.get('intent_type') || '',
    intent_title: source.searchParams.get('intent_title') || '',
    intent_description: source.searchParams.get('intent_description') || '',
    intent_cta: source.searchParams.get('intent_cta') || '',
    intent_nonce: source.searchParams.get('intent_nonce') || '',
    intent_resource: source.searchParams.get('intent_resource') || '',
  });
  const launchUrl = new URL(launchPath, window.location.origin);
  const popup = window.open(
    launchUrl.toString(),
    'meanly-connect-popup',
    'popup=yes,width=520,height=740,menubar=no,toolbar=no,location=yes,status=no,resizable=yes,scrollbars=yes',
  );

  if (!popup) {
    window.location.assign(normalized);
    return false;
  }

  try {
    popup.focus();
  } catch {
    // Focus is best-effort.
  }

  const expectedOrigin = launchUrl.origin;
  const onMessage = (messageEvent) => {
    const signal = parseSimpleL1ConnectSignal(messageEvent, expectedOrigin);
    if (!signal) {
      return;
    }

    if (typeof onSignal === 'function') {
      onSignal(signal);
    }

    if (signal.phase === 'COMPLETE') {
      window.clearInterval(closeWatch);
      window.removeEventListener('message', onMessage);
      const redirectUrl = signal.redirectUrl || signal.returnTo || '/vault?sl1_handoff=1';
      window.location.assign(redirectUrl);
    }
  };

  window.addEventListener('message', onMessage);
  const closeWatch = window.setInterval(() => {
    if (!popup || popup.closed) {
      window.clearInterval(closeWatch);
      window.removeEventListener('message', onMessage);
    }
  }, 700);

  return true;
}
