import { storefrontUrl } from './storefront-api';

const MARKET_DOMAINS = {
  global: [
    'meanly.one',
    'www.meanly.one',
    'marketplace.one',
    'www.marketplace.one',
    'meanly.test',
  ],
  ru: [
    'meanly.ru',
    'www.meanly.ru',
    'ru.marketplace.one',
    'ru.marketplace.test',
  ],
  latam_ar: [
    'meanly.ar',
    'ar.marketplace.one',
    'ar.marketplace.test',
    'digitienda.ar',
    'www.digitienda.ar',
  ],
  ge: [
    'tsipruli.ge',
    'www.tsipruli.ge',
  ],
};

export function normalizeHost(host) {
  const value = String(host || '').trim().toLowerCase();
  if (!value) {
    return '';
  }

  try {
    const parsed = new URL(value.includes('://') ? value : `https://${value}`);
    return parsed.hostname.toLowerCase();
  } catch {
    return value.split(':')[0].toLowerCase();
  }
}

export function marketKeyForHost(host) {
  const normalized = normalizeHost(host);
  if (!normalized) {
    return 'global';
  }

  for (const [marketKey, domains] of Object.entries(MARKET_DOMAINS)) {
    if (domains.includes(normalized)) {
      return marketKey;
    }
  }

  for (const [marketKey, domains] of Object.entries(MARKET_DOMAINS)) {
    if (marketKey === 'global') {
      continue;
    }

    for (const domain of domains) {
      if (normalized.endsWith(`.${domain}`)) {
        return marketKey;
      }
    }
  }

  return 'global';
}

export function storefrontHostFromEnv() {
  return normalizeHost(storefrontUrl);
}

export async function resolveStorefrontHost(headersList) {
  const forwarded = headersList?.get('x-forwarded-host')?.split(',')[0]?.trim();
  const requestHost = headersList?.get('host')?.split(':')[0]?.trim();

  return normalizeHost(forwarded || requestHost || storefrontHostFromEnv());
}

export function localeForHost(host) {
  return marketKeyForHost(host) === 'ru' ? 'ru' : 'en';
}

export function isRuMarket(marketKey) {
  return marketKey === 'ru';
}
