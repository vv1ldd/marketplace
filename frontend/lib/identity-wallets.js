/**
 * Client-side normalization for Vault Identity + bound wallet transport layers.
 *
 * API field `network_wallets` is treated as bound wallet previews until the backend
 * adopts `bound_wallets` / `identity_wallets`.
 */

import { isEvmWalletBindingKey } from './evm-wallet-discovery';

export { isEvmWalletBindingKey } from './evm-wallet-discovery';

export const WALLET_BINDING_STATE = {
  CONNECTED: 'connected',
  CONNECT: 'connect',
  PENDING: 'pending',
  COMING_SOON: 'coming_soon',
};

/** Default EVM rails for managed provisioning when API omits the list. */
export const DEFAULT_MANAGED_WALLET_NETWORK_KEYS = ['polygon', 'ethereum', 'base'];

export function readManagedWalletNetworkKeys(payload) {
  const keys = payload?.capabilities?.managed_wallet_networks
    ?? payload?.wallet_summary?.capabilities?.managed_wallet_networks;
  if (Array.isArray(keys) && keys.length > 0) {
    return new Set(keys);
  }

  return new Set(DEFAULT_MANAGED_WALLET_NETWORK_KEYS);
}

/** @deprecated use readManagedWalletNetworkKeys */
export const MANAGED_WALLET_NETWORK_KEYS = new Set(DEFAULT_MANAGED_WALLET_NETWORK_KEYS);

export function readManagedWalletsEnabled(payload) {
  return payload?.capabilities?.managed_wallets_enabled === true
    || payload?.capabilities?.can_provision_managed_wallet === true
    || payload?.wallet_summary?.capabilities?.managed_wallets_enabled === true;
}

export function readAutoProvisionOnVault(payload) {
  return payload?.capabilities?.auto_provision_on_vault === true
    || payload?.wallet_summary?.capabilities?.auto_provision_on_vault === true;
}

export function readLegacyWalletConnectEnabled(payload) {
  return payload?.capabilities?.legacy_wallet_connect_enabled === true
    || payload?.wallet_summary?.capabilities?.legacy_wallet_connect_enabled === true;
}

/** Wallet transport layers with a live connect flow in the UI. */
export const CONNECTABLE_WALLET_KEYS = new Set(['polygon', 'bitcoin', 'ethereum', 'base', 'solana', 'ton']);

/** Canonical USDC receive rails (matches verification_proofs.php). */
export const POLYGON_USDC_RECEIVE = {
  networkKey: 'polygon',
  chainId: 137,
  tokenContract: '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
  asset: 'USDC',
};

export const EVM_USDC_VALUE_ENTRY_NETWORKS = {
  polygon: {
    ...POLYGON_USDC_RECEIVE,
    label: 'Polygon',
  },
  base: {
    networkKey: 'base',
    chainId: 8453,
    tokenContract: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    asset: 'USDC',
    label: 'Base',
  },
  ethereum: {
    networkKey: 'ethereum',
    chainId: 1,
    tokenContract: '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
    asset: 'USDC',
    label: 'Ethereum',
  },
};

const VALUE_ENTRY_NETWORK_ORDER = ['polygon', 'base', 'ethereum'];

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

function buildWalletEntry(networkEntry, previewByKey, bindingByKey, identity, defaultKey, cryptoRailsEnabled, managedWalletsEnabled, managedNetworkKeys, legacyConnectEnabled) {
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
    canConnect: legacyConnectEnabled
      && CONNECTABLE_WALLET_KEYS.has(key)
      && cryptoRailsEnabled
      && networkEntry?.storefront_visible !== false
      && bindingState === WALLET_BINDING_STATE.CONNECT
      && !managedNetworkKeys.has(key),
    canCreateManaged: managedNetworkKeys.has(key)
      && managedWalletsEnabled
      && cryptoRailsEnabled
      && networkEntry?.storefront_visible !== false
      && bindingState === WALLET_BINDING_STATE.CONNECT,
    address: resolveWalletAddress(key, preview, identity, bindingRecord),
    bindingId: bindingRecord?.id || preview?.binding?.id || preview?.id || null,
    preview,
    bindingSource: bindingRecord?.binding_source || preview?.binding?.binding_source || null,
    capabilities: bindingRecord?.capabilities || null,
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

/** Connected EVM instruments that support USDC value-entry proofs. */
export function buildValueEntryReceiveOptions(wallets = []) {
  const options = wallets
    .filter((wallet) => wallet.bindingState === WALLET_BINDING_STATE.CONNECTED)
    .filter((wallet) => Boolean(EVM_USDC_VALUE_ENTRY_NETWORKS[wallet.key]))
    .filter((wallet) => Boolean(wallet.address))
    .map((wallet) => {
      const rail = EVM_USDC_VALUE_ENTRY_NETWORKS[wallet.key];

      return {
        key: wallet.key,
        address: wallet.address,
        label: wallet.label || rail.label || wallet.key,
        asset: rail.asset,
        chainId: rail.chainId,
        tokenContract: rail.tokenContract,
      };
    });

  return options.sort((left, right) => {
    const leftIndex = VALUE_ENTRY_NETWORK_ORDER.indexOf(left.key);
    const rightIndex = VALUE_ENTRY_NETWORK_ORDER.indexOf(right.key);

    return (leftIndex === -1 ? 99 : leftIndex) - (rightIndex === -1 ? 99 : rightIndex);
  });
}

/** First attached settlement instrument (any rail), not polygon-specific. */
export function resolvePrimarySettlementWallet(wallets = []) {
  return wallets.find((wallet) => (
    isSettlementInstrumentKey(wallet.key)
    && (
      wallet.bindingState === WALLET_BINDING_STATE.CONNECTED
      || wallet.bindingState === WALLET_BINDING_STATE.PENDING
    )
    && (
      Boolean(wallet.address)
      || wallet.bindingState === WALLET_BINDING_STATE.PENDING
    )
  )) || null;
}

export function isSettlementInstrumentKey(networkKey) {
  return networkKey !== 'simple-layer-1'
    && (
      Boolean(EVM_USDC_VALUE_ENTRY_NETWORKS[networkKey])
      || networkKey === BITCOIN_RECEIVE.networkKey
      || networkKey === 'solana'
      || networkKey === 'ton'
    );
}

export function hasPrimarySettlementBinding(modelOrWallets) {
  const wallets = Array.isArray(modelOrWallets)
    ? modelOrWallets
    : modelOrWallets?.wallets || [];

  return Boolean(resolvePrimarySettlementWallet(wallets));
}

/** Default rail for one-tap Safe provisioning — first enabled managed network. */
export function resolveDefaultProvisionNetworkKey(managedNetworkKeys) {
  const keys = managedNetworkKeys instanceof Set
    ? [...managedNetworkKeys]
    : Array.isArray(managedNetworkKeys) ? managedNetworkKeys : [];

  if (!keys.length) {
    return POLYGON_USDC_RECEIVE.networkKey;
  }

  if (keys.includes(POLYGON_USDC_RECEIVE.networkKey)) {
    return POLYGON_USDC_RECEIVE.networkKey;
  }

  return keys[0];
}

/**
 * Vault provisioning shell — manual first instrument when auto-bootstrap is off.
 *
 * Feature matrix:
 *   no binding + managed off     → provisioning shell
 *   no binding + managed on      → provisioning shell (legacy)
 *   no binding + auto-bootstrap  → skip (dashboard loads, server provisions)
 *   binding exists               → dashboard
 */
export function shouldShowSafeProvisioningShell(model, variant = 'wallet', options = {}) {
  if (variant !== 'vault' || !model) {
    return false;
  }

  if (options.autoProvisionOnVault === true && options.managedWalletsEnabled === true) {
    return false;
  }

  return !hasPrimarySettlementBinding(model);
}

/** @deprecated alias */
export function shouldShowSafeWelcome(model, variant = 'wallet', options = {}) {
  return shouldShowSafeProvisioningShell(model, variant, options);
}

export function shouldShowSafeDashboard(model, variant = 'wallet', options = {}) {
  if (variant !== 'vault' || !model) {
    return false;
  }

  if (options.autoProvisionOnVault === true && options.managedWalletsEnabled === true) {
    return true;
  }

  return hasPrimarySettlementBinding(model);
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
  const managedWalletsEnabled = readManagedWalletsEnabled(payload);
  const autoProvisionOnVault = readAutoProvisionOnVault(payload);
  const managedNetworkKeys = readManagedWalletNetworkKeys(payload);
  const legacyConnectEnabled = readLegacyWalletConnectEnabled(payload);
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
    managedWalletsEnabled,
    managedNetworkKeys,
    legacyConnectEnabled,
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
        managedWalletsEnabled,
        managedNetworkKeys,
        legacyConnectEnabled,
      ))
    : [];

  const primaryWallet = wallets.find((wallet) => wallet.isPrimary) || wallets[0] || null;
  const summaryCoins = aggregateSummaryCoins(wallets);

  return {
    identity,
    cryptoRailsEnabled,
    managedWalletsEnabled,
    autoProvisionOnVault,
    managedNetworkKeys,
    legacyConnectEnabled,
    defaultWalletKey: defaultKey,
    primaryWallet,
    summaryCoins: summaryCoins.length ? summaryCoins : walletCoins(primaryWallet?.preview),
    wallets,
    futureWallets,
    boundWalletPreviews: boundPreviews,
    walletBindings: bindingRecords,
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

/** Display form for the durable SL1 identity anchor — not a settlement instrument address. */
export function shortenIdentityAnchor(address, head = 10, tail = 4) {
  if (!address || typeof address !== 'string') {
    return '';
  }

  return shortenAddress(address, head, tail);
}

export function resolveIdentityAnchorAddress(identity = {}) {
  return identity?.entity_l1_address || null;
}

export function walletExternalWalletLabel(networkKey) {
  switch (networkKey) {
    case 'bitcoin':
      return 'MetaMask or Unisat';
    case 'solana':
      return 'MetaMask or Phantom';
    case 'ton':
      return 'Tonkeeper or TonConnect';
    default:
      if (isEvmWalletBindingKey(networkKey)) {
        return 'Rabby, Coinbase Wallet, or another browser wallet';
      }

      return 'your wallet';
  }
}

export function walletCapabilityNote({ nextAction, networkKey }, t) {
  const wallets = walletExternalWalletLabel(networkKey);

  switch (nextAction) {
    case 'VIEW_BOUND_WALLET':
      return t('wallet_capability_view_bound', { wallets });
    case 'NETWORK_RPC_REQUIRED':
      return t('wallet_capability_rpc_required');
    case 'CONNECT_OR_VIEW_WALLET':
      return t('wallet_capability_connect_or_view');
    case 'NETWORK_COMING_SOON':
      return t('wallet_capability_coming_soon');
    default:
      return t('wallet_binding_capabilities_note', { wallets });
  }
}

export function walletConnectIntroKey(networkKey) {
  if (networkKey === 'ton') {
    return 'wallet_ton_connect_intro';
  }

  if (networkKey === 'bitcoin') {
    return 'wallet_bitcoin_connect_prompt';
  }

  if (networkKey === 'solana') {
    return 'wallet_solana_connect_intro';
  }

  if (isEvmWalletBindingKey(networkKey)) {
    return 'wallet_evm_connect_intro';
  }

  return 'wallet_connect_intro';
}

export function walletCapabilityNoteTone(nextAction) {
  switch (nextAction) {
    case 'VIEW_BOUND_WALLET':
      return 'live';
    case 'NETWORK_RPC_REQUIRED':
      return 'warn';
    default:
      return 'default';
  }
}
