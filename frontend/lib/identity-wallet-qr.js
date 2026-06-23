import { ensureQrDataUrl } from './handoff-qr';
import {
  BITCOIN_RECEIVE,
  EVM_USDC_VALUE_ENTRY_NETWORKS,
  POLYGON_USDC_RECEIVE,
} from './identity-wallets';

const EVM_ADDRESS_PATTERN = /^0x[a-fA-F0-9]{40}$/;
const BITCOIN_ADDRESS_PATTERN = /^(bc1[a-z0-9]+|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/i;

export function normalizeEvmAddress(address) {
  const normalized = String(address || '').trim();
  if (!EVM_ADDRESS_PATTERN.test(normalized)) {
    return '';
  }

  return normalized;
}

export function buildEvmUsdcReceiveQrUrl(evmAddress, networkKey = POLYGON_USDC_RECEIVE.networkKey) {
  const recipient = normalizeEvmAddress(evmAddress);
  const rail = EVM_USDC_VALUE_ENTRY_NETWORKS[networkKey];
  if (!recipient || !rail) {
    return '';
  }

  return `ethereum:${rail.tokenContract}@${rail.chainId}/transfer?address=${recipient}`;
}

export async function buildEvmUsdcReceiveQrDataUrl(evmAddress, networkKey = POLYGON_USDC_RECEIVE.networkKey) {
  const targetUrl = buildEvmUsdcReceiveQrUrl(evmAddress, networkKey);
  if (!targetUrl) {
    return '';
  }

  return ensureQrDataUrl(targetUrl, {
    width: 256,
    margin: 1,
    errorCorrectionLevel: 'M',
  });
}

export function buildPolygonUsdcReceiveQrUrl(evmAddress) {
  return buildEvmUsdcReceiveQrUrl(evmAddress, POLYGON_USDC_RECEIVE.networkKey);
}

export async function buildPolygonUsdcReceiveQrDataUrl(evmAddress) {
  return buildEvmUsdcReceiveQrDataUrl(evmAddress, POLYGON_USDC_RECEIVE.networkKey);
}

export function normalizeBitcoinAddress(address) {
  const normalized = String(address || '').trim();
  if (!BITCOIN_ADDRESS_PATTERN.test(normalized)) {
    return '';
  }

  return normalized;
}

export function buildBitcoinReceiveQrUrl(bitcoinAddress) {
  const recipient = normalizeBitcoinAddress(bitcoinAddress);
  if (!recipient) {
    return '';
  }

  return `bitcoin:${recipient}`;
}

export async function buildBitcoinReceiveQrDataUrl(bitcoinAddress) {
  const targetUrl = buildBitcoinReceiveQrUrl(bitcoinAddress);
  if (!targetUrl) {
    return '';
  }

  return ensureQrDataUrl(targetUrl, {
    width: 256,
    margin: 1,
    errorCorrectionLevel: 'M',
  });
}
