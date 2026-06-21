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

  connectUrl.searchParams.delete('popup');

  return `${connectUrl.pathname}${connectUrl.search}${connectUrl.hash}`;
}

export function handleSimpleL1ConnectClick(event, href) {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return false;
  }

  if (!isSimpleL1ConnectHref(href)) {
    return false;
  }

  event.preventDefault();
  window.location.assign(normalizeSimpleL1ConnectHref(href));
  return true;
}
