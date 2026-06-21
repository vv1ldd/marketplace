import { getWallets } from '@wallet-standard/core';
import { normalizeBitcoinSignature } from './bitcoin-signature';
import { WalletConnectError } from './wallet-connect-error';
import { ensureMetaMaskMultichainRegistered, hasMetaMaskProvider, signMetaMaskBitcoinMessage } from './metamask-multichain-bootstrap';

const BITCOIN_WALLET_WAIT_MS = 2500;

function findBitcoinStandardWallet(wallets = []) {
  const candidates = wallets.filter((wallet) => (
    wallet.features?.['bitcoin:connect']
    && wallet.features?.['bitcoin:signMessage']
  ));

  return candidates.find((wallet) => wallet?.name?.toLowerCase().includes('metamask'))
    || candidates[0]
    || null;
}

async function waitForBitcoinStandardWallet() {
  const registry = getWallets();
  const immediate = findBitcoinStandardWallet(registry.get());
  if (immediate) {
    return immediate;
  }

  return new Promise((resolve) => {
    let settled = false;
    const finish = (wallet) => {
      if (settled) {
        return;
      }
      settled = true;
      off();
      clearTimeout(timer);
      resolve(wallet);
    };

    const off = registry.on('register', () => {
      const wallet = findBitcoinStandardWallet(registry.get());
      if (wallet) {
        finish(wallet);
      }
    });

    const timer = setTimeout(() => {
      finish(findBitcoinStandardWallet(registry.get()));
    }, BITCOIN_WALLET_WAIT_MS);
  });
}

function createBitcoinStandardConnect(wallet) {
  let connectedAccounts = [];
  const isMetaMaskWallet = wallet?.name?.toLowerCase().includes('metamask');

  async function ensureConnectedAccount(preferredAddress = '') {
    if (connectedAccounts.length) {
      const existing = connectedAccounts.find((entry) => entry?.address === preferredAddress)
        || connectedAccounts[0];
      if (existing?.address) {
        return existing;
      }
    }

    const { accounts } = await wallet.features['bitcoin:connect'].connect({
      purposes: ['payment'],
    });
    connectedAccounts = Array.isArray(accounts) ? accounts : [];

    const account = connectedAccounts.find((entry) => entry?.address === preferredAddress)
      || connectedAccounts[0];

    if (!account?.address) {
      throw new WalletConnectError('no_account', 'No Bitcoin account was returned.');
    }

    return account;
  }

  return {
    id: isMetaMaskWallet ? 'metamask' : 'bitcoin-standard',
    requestAccounts: async () => {
      const account = await ensureConnectedAccount();
      return [account.address];
    },
    signMessage: async (message, address) => {
      const account = await ensureConnectedAccount(address);

      if (isMetaMaskWallet) {
        try {
          return normalizeBitcoinSignature(
            await signMetaMaskBitcoinMessage(message, account.address),
          );
        } catch (error) {
          if (error instanceof WalletConnectError) {
            throw error;
          }

          throw new WalletConnectError(
            'wallet_error',
            error?.message || 'MetaMask could not sign the Bitcoin ownership message.',
            error,
          );
        }
      }

      const result = await wallet.features['bitcoin:signMessage'].signMessage({
        account,
        message,
      });

      const signature = normalizeBitcoinSignature(result);
      if (!signature) {
        throw new WalletConnectError(
          'wallet_error',
          'Bitcoin wallet returned an empty ownership signature.',
        );
      }

      return signature;
    },
  };
}

function readNativeBitcoinProvider() {
  if (typeof window === 'undefined') {
    return null;
  }

  if (window.unisat) {
    return { id: 'unisat', api: window.unisat };
  }

  if (window.okxwallet?.bitcoin) {
    return { id: 'okx', api: window.okxwallet.bitcoin };
  }

  const xverse = window.XverseProviders?.BitcoinProvider;
  if (xverse) {
    return { id: 'xverse', api: xverse };
  }

  return null;
}

function createNativeBitcoinConnect(providerEntry) {
  const { api } = providerEntry;

  return {
    id: providerEntry.id,
    requestAccounts: async () => {
      if (typeof api.requestAccounts === 'function') {
        return api.requestAccounts();
      }

      if (typeof api.connect === 'function') {
        const result = await api.connect();
        return result?.address ? [result.address] : result?.accounts || [];
      }

      throw new WalletConnectError(
        'wallet_error',
        'This Bitcoin wallet does not expose a connect API.',
      );
    },
    signMessage: async (message, address) => {
      if (typeof api.signMessage !== 'function') {
        throw new WalletConnectError(
          'wallet_error',
          'This Bitcoin wallet cannot sign ownership messages yet.',
        );
      }

      try {
        return normalizeBitcoinSignature(await api.signMessage(message, 'ecdsa'));
      } catch (error) {
        if (error instanceof WalletConnectError) {
          throw error;
        }

        return normalizeBitcoinSignature(await api.signMessage({ address, message }));
      }
    },
  };
}

function hasEthereumProvider() {
  return hasMetaMaskProvider();
}

export async function resolveBitcoinConnect() {
  if (hasEthereumProvider()) {
    await ensureMetaMaskMultichainRegistered();
  }

  const bitcoinWallet = await waitForBitcoinStandardWallet();
  if (bitcoinWallet) {
    return createBitcoinStandardConnect(bitcoinWallet);
  }

  const nativeProvider = readNativeBitcoinProvider();
  if (nativeProvider) {
    return createNativeBitcoinConnect(nativeProvider);
  }

  if (hasEthereumProvider()) {
    throw new WalletConnectError(
      'bitcoin_connect_failed',
      'MetaMask could not open Bitcoin on this page. Reload the page and try Connect again.',
    );
  }

  return null;
}
