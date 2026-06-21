/**
 * Client-side normalization for Vault Identity + bound wallet transport layers.
 *
 * API field `network_wallets` is treated as bound wallet previews until the backend
 * adopts `bound_wallets` / `identity_wallets`.
 */

export const WALLET_BINDING_STATE = {
  CONNECTED: 'connected',
  CONNECT: 'connect',
  PENDING: 'pending',
  COMING_SOON: 'coming_soon',
};

/** Wallet transport layers with a live connect flow in the UI. */
export const CONNECTABLE_WALLET_KEYS = new Set(['polygon', 'bitcoin', 'ethereum', 'base', 'solana']);

/** Canonical USDC receive rail for connected Polygon wallets (matches verification_proofs.php). */
export const POLYGON_USDC_RECEIVE = {
  networkKey: 'polygon',
  chainId: 137,
  tokenContract: '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
  asset: 'USDC',
};

/** Canonical BTC receive rail for connected Bitcoin wallets. */
export const BITCOIN_RECEIVE = {
  networkKey: 'bitcoin',
  asset: 'BTC',
};

const FUTURE_WALLET_BINDINGS = [];

const WALLET_META_KEYS = new Set([
  'identity',
  'settlement_network',
  'settlement_networks',
  'network_wallets',
  'bound_wallets',
  'identity_wallets',
  'wallet_summary',
  'wallet_bindings',
  'capabilities',
  'vault',
  'bindings_contract',
  'bindings_vault_id',
]);

function extractWalletPreview(payload) {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  const preview = {};
  for (const [key, value] of Object.entries(payload)) {
    if (!WALLET_META_KEYS.has(key)) {
      preview[key] = value;
    }
  }

  return preview.network ? preview : null;
}

function readBoundWalletPreviews(payload) {
  const previews = Array.isArray(payload?.bound_wallets)
    ? payload.bound_wallets
    : Array.isArray(payload?.identity_wallets)
      ? payload.identity_wallets
      : Array.isArray(payload?.network_wallets)
        ? payload.network_wallets
        : [];

  return previews.filter((entry) => entry?.network?.key);
}

export function readCryptoRailsEnabled(payload) {
  return payload?.capabilities?.crypto_rails_enabled === true
    || payload?.wallet_summary?.capabilities?.crypto_rails_enabled === true;
}

function catalogItems(payload) {
  return Array.isArray(payload?.settlement_networks?.items)
    ? payload.settlement_networks.items
    : [];
}

function resolveBindingState(networkEntry, preview, identity, bindingRecord, cryptoRailsEnabled) {
  const key = networkEntry?.key;
  const binding = preview?.binding || bindingRecord || null;
  const verificationState = binding?.verification_state || null;

  if (verificationState === 'verified' || binding?.state === 'connected') {
    return WALLET_BINDING_STATE.CONNECTED;
  }

  if (verificationState === 'pending') {
    return WALLET_BINDING_STATE.PENDING;
  }

  if (verificationState === 'revoked') {
    if (CONNECTABLE_WALLET_KEYS.has(key) && cryptoRailsEnabled) {
      return WALLET_BINDING_STATE.CONNECT;
    }

    return preview ? WALLET_BINDING_STATE.CONNECT : WALLET_BINDING_STATE.COMING_SOON;
  }

  if (key === 'simple-layer-1') {
    return identity?.entity_l1_address ? WALLET_BINDING_STATE.CONNECTED : WALLET_BINDING_STATE.CONNECT;
  }

  if (
    CONNECTABLE_WALLET_KEYS.has(key)
    && cryptoRailsEnabled
    && networkEntry?.storefront_visible !== false
  ) {
    return WALLET_BINDING_STATE.CONNECT;
  }

  if (preview && networkEntry?.storefront_visible !== false) {
    if (networkEntry?.enabled === true) {
      return WALLET_BINDING_STATE.CONNECT;
    }

    if (networkEntry?.status === 'coming_soon' || networkEntry?.enabled === false) {
      return WALLET_BINDING_STATE.CONNECT;
    }
  }

  return WALLET_BINDING_STATE.COMING_SOON;
}

function resolveWalletAddress(networkKey, preview, identity, bindingRecord) {
  const bindingAddress = preview?.binding?.address
    || preview?.address
    || bindingRecord?.binding_value
    || null;
  if (bindingAddress) {
    return bindingAddress;
  }

  if (networkKey === 'simple-layer-1') {
    return identity?.entity_l1_address || null;
  }

  return null;
}

function buildWalletEntry(networkEntry, previewByKey, bindingByKey, identity, defaultKey, cryptoRailsEnabled) {
  const key = networkEntry.key;
  const preview = previewByKey.get(key) || null;
  const bindingRecord = bindingByKey.get(key) || null;
  const bindingState = resolveBindingState(
    networkEntry,
    preview,
    identity,
    bindingRecord,
    cryptoRailsEnabled,
  );

  return {
    key,
    label: networkEntry.label || key,
    protocol: networkEntry.protocol || null,
    networkStatus: networkEntry.status || null,
    bindingState,
    verificationState: preview?.binding?.verification_state || bindingRecord?.verification_state || null,
    canConnect: CONNECTABLE_WALLET_KEYS.has(key)
      && cryptoRailsEnabled
      && networkEntry?.storefront_visible !== false
      && bindingState === WALLET_BINDING_STATE.CONNECT,
    address: resolveWalletAddress(key, preview, identity, bindingRecord),
    preview,
    isPrimary: key === defaultKey,
  };
}

/**
 * @param {Record<string, unknown> | null | undefined} payload
 */
export function visibleWalletBindings(wallets = []) {
  return wallets.filter(
    (wallet) => !(
      wallet.key === 'simple-layer-1'
      && wallet.isPrimary
      && wallet.bindingState === WALLET_BINDING_STATE.CONNECTED
    ),
  );
}

export function resolveConnectedPolygonWallet(wallets = []) {
  return wallets.find(
    (wallet) => wallet.key === POLYGON_USDC_RECEIVE.networkKey
      && wallet.bindingState === WALLET_BINDING_STATE.CONNECTED
      && wallet.address,
  ) || null;
}

export function resolvePolygonWalletEntry(wallets = []) {
  return wallets.find((wallet) => wallet.key === POLYGON_USDC_RECEIVE.networkKey) || null;
}

export function resolveConnectedBitcoinWallet(wallets = []) {
  return wallets.find(
    (wallet) => wallet.key === BITCOIN_RECEIVE.networkKey
      && wallet.bindingState === WALLET_BINDING_STATE.CONNECTED
      && wallet.address,
  ) || null;
}

export function resolveBitcoinWalletEntry(wallets = []) {
  return wallets.find((wallet) => wallet.key === BITCOIN_RECEIVE.networkKey) || null;
}

export function walletUsdcCoins(walletBinding) {
  return walletCoins(walletBinding?.preview).filter((coin) => String(coin?.symbol || '').toUpperCase() === 'USDC');
}

export function walletBtcCoins(walletBinding) {
  return walletCoins(walletBinding?.preview).filter((coin) => String(coin?.symbol || '').toUpperCase() === 'BTC');
}

function parseCoinAmount(coin = {}) {
  const amount = Number(coin.amount ?? 0);
  return Number.isFinite(amount) ? amount : 0;
}

function formatAggregatedDisplay(symbol, amount) {
  if (!Number.isFinite(amount)) {
    return `0 ${symbol}`;
  }

  const normalized = Number.isInteger(amount)
    ? String(amount)
    : amount.toFixed(8).replace(/\.?0+$/, '');

  return `${normalized} ${symbol}`;
}

export function aggregateSummaryCoins(wallets = []) {
  const totals = new Map();

  for (const wallet of wallets) {
    if (wallet.bindingState !== WALLET_BINDING_STATE.CONNECTED) {
      continue;
    }

    for (const coin of walletCoins(wallet.preview)) {
      const symbol = String(coin.symbol || '').toUpperCase();
      if (!symbol) {
        continue;
      }

      const amount = parseCoinAmount(coin);
      const existing = totals.get(symbol);

      if (!existing) {
        totals.set(symbol, {
          key: coin.key || symbol,
          symbol,
          name: coin.name || symbol,
          amount,
          display_amount: coin.display_amount || formatAggregatedDisplay(symbol, amount),
          status: coin.status || 'available',
        });
        continue;
      }

      existing.amount += amount;
      existing.display_amount = formatAggregatedDisplay(symbol, existing.amount);
      if (existing.status !== 'available' && coin.status === 'available') {
        existing.status = 'available';
      }
    }
  }

  return [...totals.values()];
}

export function resolveIdentityWalletModel(payload) {
  const identity = payload?.identity || {};
  const cryptoRailsEnabled = readCryptoRailsEnabled(payload);
  const defaultKey = payload?.settlement_networks?.default
    || payload?.settlement_network?.key
    || payload?.network?.key
    || 'simple-layer-1';

  const defaultPreview = extractWalletPreview(payload);
  const boundPreviews = readBoundWalletPreviews(payload);
  const previewByKey = new Map();
  const bindingRecords = Array.isArray(payload?.wallet_bindings)
    ? payload.wallet_bindings
    : [];
  const bindingByKey = new Map(
    bindingRecords
      .filter((entry) => entry?.binding_type === 'wallet' && entry?.binding_key)
      .map((entry) => [entry.binding_key, entry]),
  );

  if (defaultPreview?.network?.key) {
    previewByKey.set(defaultPreview.network.key, defaultPreview);
  }

  for (const preview of boundPreviews) {
    previewByKey.set(preview.network.key, preview);
  }

  const wallets = catalogItems(payload).map((networkEntry) => buildWalletEntry(
    networkEntry,
    previewByKey,
    bindingByKey,
    identity,
    defaultKey,
    cryptoRailsEnabled,
  ));

  const catalogKeys = new Set(wallets.map((wallet) => wallet.key));
  const futureWallets = cryptoRailsEnabled
    ? FUTURE_WALLET_BINDINGS
      .filter((entry) => !catalogKeys.has(entry.key))
      .map((networkEntry) => buildWalletEntry(
        networkEntry,
        previewByKey,
        bindingByKey,
        identity,
        defaultKey,
        cryptoRailsEnabled,
      ))
    : [];

  const primaryWallet = wallets.find((wallet) => wallet.isPrimary) || wallets[0] || null;
  const summaryCoins = aggregateSummaryCoins(wallets);

  return {
    identity,
    cryptoRailsEnabled,
    defaultWalletKey: defaultKey,
    primaryWallet,
    summaryCoins: summaryCoins.length ? summaryCoins : walletCoins(primaryWallet?.preview),
    wallets,
    futureWallets,
    boundWalletPreviews: boundPreviews,
  };
}

export function walletCoins(preview) {
  if (!preview) {
    return [];
  }

  if (Array.isArray(preview.coins)) {
    return preview.coins;
  }

  if (Array.isArray(preview.assets)) {
    return preview.assets;
  }

  return [];
}

export function connectedWalletBindings(wallets = []) {
  return wallets.filter((wallet) => wallet.bindingState === WALLET_BINDING_STATE.CONNECTED);
}

export function vaultBalanceObservationState(wallets = []) {
  const connected = connectedWalletBindings(wallets);
  if (!connected.length) {
    return 'none';
  }

  const coins = connected.flatMap((wallet) => walletCoins(wallet.preview));
  if (coins.some((coin) => coin?.status === 'balance_unavailable')) {
    return 'unavailable';
  }

  if (coins.some((coin) => parseCoinAmount(coin) > 0)) {
    return 'positive';
  }

  return 'zero';
}

export function shortenAddress(address, head = 6, tail = 4) {
  if (!address || typeof address !== 'string') {
    return '';
  }

  if (address.length <= head + tail + 3) {
    return address;
  }

  return `${address.slice(0, head)}…${address.slice(-tail)}`;
}
