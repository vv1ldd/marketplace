import { WalletConnectError } from './wallet-connect-error';
import { normalizeSolanaSignature } from './solana-signature';
import {
  ensureMetaMaskMultichainRegistered,
  hasMetaMaskProvider,
  requestMetaMaskSolanaAccounts,
  signMetaMaskSolanaMessage,
} from './metamask-multichain-bootstrap';

function encodeBase64(bytes) {
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(bytes).toString('base64');
  }

  let binary = '';
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }

  return btoa(binary);
}

function readNativeSolanaProvider() {
  if (typeof window === 'undefined') {
    return null;
  }

  if (window.phantom?.solana?.isPhantom) {
    return window.phantom.solana;
  }

  if (window.solana?.isPhantom) {
    return window.solana;
  }

  if (window.solflare?.isSolflare) {
    return window.solflare;
  }

  return window.solana || null;
}

function createNativeSolanaConnect(provider) {
  return {
    id: provider.isPhantom ? 'phantom' : 'solana-native',
    requestAccounts: async () => {
      const response = typeof provider.connect === 'function'
        ? await provider.connect()
        : null;

      const publicKey = response?.publicKey || provider.publicKey;
      const address = typeof publicKey?.toString === 'function'
        ? publicKey.toString()
        : typeof publicKey === 'string'
          ? publicKey
          : null;

      if (!address) {
        throw new WalletConnectError('no_account', 'No Solana account was returned.');
      }

      return [address];
    },
    signMessage: async (message) => {
      if (typeof provider.signMessage !== 'function') {
        throw new WalletConnectError(
          'wallet_error',
          'This Solana wallet cannot sign ownership messages yet.',
        );
      }

      const encodedMessage = new TextEncoder().encode(message);
      const result = await provider.signMessage(encodedMessage, 'utf8');
      const signature = result?.signature || result;

      if (!signature || typeof signature.length !== 'number') {
        throw new WalletConnectError(
          'wallet_error',
          'Solana wallet returned an empty ownership signature.',
        );
      }

      return normalizeSolanaSignature(signature);
    },
  };
}

function createMetaMaskSolanaConnect() {
  return {
    id: 'metamask',
    requestAccounts: async () => {
      try {
        return await requestMetaMaskSolanaAccounts();
      } catch (error) {
        throw new WalletConnectError(
          'wallet_error',
          error?.message || 'MetaMask could not open Solana on this page.',
          error,
        );
      }
    },
    signMessage: async (message, address) => {
      try {
        return await signMetaMaskSolanaMessage(message, address);
      } catch (error) {
        throw new WalletConnectError(
          'wallet_error',
          error?.message || 'MetaMask could not sign the Solana ownership message.',
          error,
        );
      }
    },
  };
}

export async function resolveSolanaConnect() {
  if (hasMetaMaskProvider()) {
    const registered = await ensureMetaMaskMultichainRegistered();
    if (registered) {
      return createMetaMaskSolanaConnect();
    }
  }

  const provider = readNativeSolanaProvider();
  if (provider) {
    return createNativeSolanaConnect(provider);
  }

  if (hasMetaMaskProvider()) {
    throw new WalletConnectError(
      'solana_connect_failed',
      'MetaMask could not open Solana on this page. Reload the page and try Connect again.',
    );
  }

  return null;
}
