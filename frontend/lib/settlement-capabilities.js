import { WALLET_BINDING_STATE } from './identity-wallets';

const PAYMENT_ROUTING_NETWORKS = new Set(['polygon', 'base', 'ethereum']);
const PAYMENT_ROUTING_ASSETS = ['USDC'];

function readIdentityPaymentsEnabled(payload) {
  return payload?.capabilities?.identity_payments_enabled === true
    || payload?.wallet_summary?.capabilities?.identity_payments_enabled === true;
}

export function capabilityEnabled(capability) {
  return capability === true || capability?.enabled === true;
}

export function capabilityMatrixForWallet(walletBinding, options = {}) {
  if (walletBinding?.capabilities) {
    return walletBinding.capabilities;
  }

  const verified = walletBinding?.bindingState === WALLET_BINDING_STATE.CONNECTED;
  const managed = walletBinding?.bindingSource === 'managed';
  const protocol = walletBinding?.preview?.binding?.protocol
    || walletBinding?.protocol
    || (walletBinding?.key === 'bitcoin' ? 'utxo' : null);
  const isManagedEvm = verified && managed && protocol === 'evm';
  const paymentsEnabled = options.identityPaymentsEnabled === true;
  const paymentsExecute = options.identityPaymentsExecute === true;
  const network = walletBinding?.key || '';
  const receiveAsset = instrumentReceiveAsset(network, protocol);

  const paymentRoutingAssets = isManagedEvm
    && paymentsEnabled
    && PAYMENT_ROUTING_NETWORKS.has(network)
    ? PAYMENT_ROUTING_ASSETS
    : [];

  return {
    instrument: network,
    instrument_label: walletBinding?.label || network,
    binding_source: walletBinding?.bindingSource || 'external',
    receive: {
      enabled: verified,
      asset: receiveAsset,
    },
    send: {
      enabled: isManagedEvm && paymentsExecute,
      assets: isManagedEvm && paymentsExecute ? PAYMENT_ROUTING_ASSETS : [],
    },
    payment_routing: {
      enabled: paymentRoutingAssets.length > 0,
      assets: paymentRoutingAssets,
    },
  };
}

export function capabilityMatrixFromBindingRecord(bindingRecord) {
  if (bindingRecord?.capabilities) {
    return bindingRecord.capabilities;
  }

  return null;
}

export function buildInstrumentCapabilityRows(wallets = [], options = {}) {
  return wallets
    .filter((wallet) => wallet.bindingState === WALLET_BINDING_STATE.CONNECTED)
    .map((wallet) => {
      const fromApi = capabilityMatrixFromBindingRecord(
        options.bindingByKey?.get?.(wallet.key),
      );

      return fromApi || capabilityMatrixForWallet(wallet, options);
    });
}

export function hasOutgoingPaymentRouting(matrixRows = []) {
  return matrixRows.some((row) => capabilityEnabled(row?.payment_routing));
}

export function outgoingPaymentAssets(matrixRows = []) {
  const assets = new Set();
  for (const row of matrixRows) {
    if (!capabilityEnabled(row?.payment_routing)) {
      continue;
    }

    for (const asset of row.payment_routing?.assets || []) {
      assets.add(asset);
    }
  }

  return [...assets];
}

export function readIdentityPaymentFlags(payload) {
  return {
    identityPaymentsEnabled: readIdentityPaymentsEnabled(payload),
    identityPaymentsExecute: payload?.capabilities?.identity_payments_execute === true
      || payload?.wallet_summary?.capabilities?.identity_payments_execute === true,
    identityPaymentDisputesEnabled: payload?.capabilities?.identity_payment_disputes_enabled === true
      || payload?.wallet_summary?.capabilities?.identity_payment_disputes_enabled === true,
  };
}

function instrumentReceiveAsset(networkKey, protocol) {
  if (protocol === 'utxo' || networkKey === 'bitcoin') return 'BTC';
  if (protocol === 'solana' || networkKey === 'solana') return 'USDC';
  if (protocol === 'ton' || networkKey === 'ton') return 'USDC';
  return 'USDC';
}
