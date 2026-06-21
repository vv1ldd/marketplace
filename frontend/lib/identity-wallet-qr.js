import { ensureQrDataUrl } from './handoff-qr';
import { BITCOIN_RECEIVE, POLYGON_USDC_RECEIVE } from './identity-wallets';

const EVM_ADDRESS_PATTERN = /^0x[a-fA-F0-9]{40}$/;
const BITCOIN_ADDRESS_PATTERN = /^(bc1[a-z0-9]+|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/i;

export function normalizeEvmAddress(address) {
  const normalized = String(address || '').trim();
  if (!EVM_ADDRESS_PATTERN.test(normalized)) {
    return '';
  }

  return normalized;
}

export function buildPolygonUsdcReceiveQrUrl(evmAddress) {
  const recipient = normalizeEvmAddress(evmAddress);
  if (!recipient) {
    return '';
  }

  const { tokenContract, chainId } = POLYGON_USDC_RECEIVE;

  return `ethereum:${tokenContract}@${chainId}/transfer?address=${recipient}`;
}

export async function buildPolygonUsdcReceiveQrDataUrl(evmAddress) {
  const targetUrl = buildPolygonUsdcReceiveQrUrl(evmAddress);
  if (!targetUrl) {
    return '';
  }

  return ensureQrDataUrl(targetUrl, {
    width: 256,
    margin: 1,
    errorCorrectionLevel: 'M',
  });
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
