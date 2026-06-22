export const EVM_WALLET_BINDING_KEYS = new Set(['polygon', 'ethereum', 'base']);

export const EVM_SIGNATURE_SCHEME = 'evm_personal_sign';

const DISCOVERY_TIMEOUT_MS = 500;

let cachedProviders = null;
let discoveryPromise = null;

export function isEvmWalletBindingKey(bindingKey) {
  return EVM_WALLET_BINDING_KEYS.has(bindingKey);
}

function inferLegacyProviderId(provider) {
  if (provider?.isRabby) {
    return 'io.rabby';
  }

  if (provider?.isCoinbaseWallet) {
    return 'com.coinbase.wallet';
  }

  if (provider?.isTrust || provider?.isTrustWallet) {
    return 'com.trustwallet.app';
  }

  if (provider?.isPhantom) {
    return 'app.phantom';
  }

  if (provider?.isMetaMask) {
    return 'io.metamask';
  }

  if (provider?.isBraveWallet) {
    return 'com.brave.wallet';
  }

  return 'injected.unknown';
}

function inferLegacyProviderLabel(provider) {
  if (provider?.isRabby) {
    return 'Rabby';
  }

  if (provider?.isCoinbaseWallet) {
    return 'Coinbase Wallet';
  }

  if (provider?.isTrust || provider?.isTrustWallet) {
    return 'Trust Wallet';
  }

  if (provider?.isPhantom) {
    return 'Phantom';
  }

  if (provider?.isMetaMask) {
    return 'MetaMask';
  }

  if (provider?.isBraveWallet) {
    return 'Brave Wallet';
  }

  return 'Browser wallet';
}

function normalizeEip6963Provider(detail) {
  const info = detail?.info || {};

  return {
    provider: detail.provider,
    providerId: info.rdns || detail.uuid || 'injected.unknown',
    uuid: info.uuid || null,
    label: info.name || 'Wallet',
    icon: info.icon || null,
    source: 'eip6963',
  };
}

function normalizeLegacyProvider(provider) {
  return {
    provider,
    providerId: inferLegacyProviderId(provider),
    uuid: null,
    label: inferLegacyProviderLabel(provider),
    icon: null,
    source: 'legacy',
  };
}

function registerProvider(registry, entry) {
  if (!entry?.provider || !entry.providerId) {
    return;
  }

  const existing = registry.get(entry.providerId);
  if (existing) {
    if (existing.source === 'eip6963' && entry.source === 'legacy') {
      return;
    }

    if (existing.source === entry.source && existing.provider === entry.provider) {
      return;
    }
  }

  registry.set(entry.providerId, entry);
}

export function listLegacyEvmProviders() {
  if (typeof window === 'undefined' || !window.ethereum) {
    return [];
  }

  const providers = Array.isArray(window.ethereum.providers) ? window.ethereum.providers : [window.ethereum];

  return providers.map((provider) => normalizeLegacyProvider(provider));
}

function registerLegacyProviders(registry) {
  for (const entry of listLegacyEvmProviders()) {
    registerProvider(registry, entry);
  }
}

export function clearEvmProviderDiscoveryCache() {
  cachedProviders = null;
  discoveryPromise = null;
}

/**
 * Warm EIP-6963 discovery when the Vault wallet panel mounts.
 */
export function warmEvmProviderDiscovery(options = {}) {
  if (typeof window === 'undefined') {
    return Promise.resolve([]);
  }

  if (cachedProviders && !options.forceRefresh) {
    return Promise.resolve(cachedProviders);
  }

  if (!discoveryPromise || options.forceRefresh) {
    discoveryPromise = discoverEvmProviders({
      ...options,
      forceRefresh: true,
    }).finally(() => {
      discoveryPromise = null;
    });
  }

  return discoveryPromise;
}

/**
 * Discover injected EVM wallets via EIP-6963 with a legacy fallback.
 *
 * @param {{ timeoutMs?: number, forceRefresh?: boolean }} [options]
 * @returns {Promise<Array<{ provider: object, providerId: string, uuid: string | null, label: string, icon: string | null, source: 'eip6963' | 'legacy' }>>}
 */
export async function discoverEvmProviders(options = {}) {
  if (typeof window === 'undefined') {
    return [];
  }

  if (cachedProviders && !options.forceRefresh) {
    return cachedProviders;
  }

  const timeoutMs = options.timeoutMs ?? DISCOVERY_TIMEOUT_MS;
  const registry = new Map();

  const onAnnounce = (event) => {
    const detail = event?.detail;
    if (!detail?.provider) {
      return;
    }

    registerProvider(registry, normalizeEip6963Provider(detail));
  };

  window.addEventListener('eip6963:announceProvider', onAnnounce);
  window.dispatchEvent(new Event('eip6963:requestProvider'));

  await new Promise((resolve) => {
    window.setTimeout(resolve, timeoutMs);
  });

  window.removeEventListener('eip6963:announceProvider', onAnnounce);
  registerLegacyProviders(registry);

  cachedProviders = Array.from(registry.values()).sort((left, right) => left.label.localeCompare(right.label));

  return cachedProviders;
}

export function normalizeEip6963ProviderDetail(detail) {
  return normalizeEip6963Provider(detail);
}

export function normalizeLegacyProviderDetail(provider) {
  return normalizeLegacyProvider(provider);
}

export function buildProviderRegistryFromEntries(entries = []) {
  const registry = new Map();

  for (const entry of entries) {
    registerProvider(registry, entry);
  }

  return Array.from(registry.values()).sort((left, right) => left.label.localeCompare(right.label));
}
