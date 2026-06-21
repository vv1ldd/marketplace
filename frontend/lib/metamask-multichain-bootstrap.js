import { getWallets } from '@wallet-standard/core';
import { normalizeBitcoinSignature } from './bitcoin-signature';
import { normalizeSolanaSignature } from './solana-signature';

const BITCOIN_SCOPES = [
  'bip122:000000000019d6689c085ae165831e93',
  'bip122:000000000933ea01ad0ee984209779ba',
  'bip122:00000000da84f2bafbbc53dee25a72ae',
  'bip122:regtest',
];

const SOLANA_SCOPES = [
  'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
  'solana:mainnet',
];

let registrationPromise = null;
let registered = false;
let multichainClient = null;

export function isMetaMaskBitcoinRegistered() {
  return registered;
}

export function isMetaMaskMultichainRegistered() {
  return registered;
}

export function hasMetaMaskProvider() {
  return typeof window !== 'undefined' && Boolean(readMetaMaskEthereumProvider());
}

export function readMetaMaskEthereumProvider() {
  if (typeof window === 'undefined') {
    return null;
  }

  if (Array.isArray(window.ethereum?.providers)) {
    return window.ethereum.providers.find((provider) => provider.isMetaMask) || null;
  }

  return window.ethereum?.isMetaMask ? window.ethereum : null;
}

function encodeUtf8Base64(value) {
  const bytes = new TextEncoder().encode(value);
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(bytes).toString('base64');
  }

  let binary = '';
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }

  return btoa(binary);
}

function caip10Address(accountId) {
  if (typeof accountId !== 'string') {
    return null;
  }

  const parts = accountId.split(':');
  return parts.length >= 3 ? parts[parts.length - 1] : accountId;
}

async function resolveBitcoinScope(client) {
  const session = await client.getSession();
  const sessionScopes = session?.sessionScopes ?? {};

  for (const scope of BITCOIN_SCOPES) {
    if (sessionScopes[scope]?.accounts?.length) {
      return scope;
    }
  }

  return BITCOIN_SCOPES[0];
}

async function resolveSolanaScope(client) {
  const session = await client.getSession();
  const sessionScopes = session?.sessionScopes ?? {};

  for (const scope of SOLANA_SCOPES) {
    if (sessionScopes[scope]?.accounts?.length) {
      return scope;
    }
  }

  return SOLANA_SCOPES[0];
}

async function ensureSolanaSession(client) {
  const existingScope = await resolveSolanaScope(client);
  const session = await client.getSession();
  const accounts = session?.sessionScopes?.[existingScope]?.accounts ?? [];

  if (accounts.length) {
    return {
      scope: existingScope,
      accounts: accounts.map((entry) => caip10Address(entry)).filter(Boolean),
    };
  }

  await client.createSession({
    optionalScopes: {
      [SOLANA_SCOPES[0]]: {
        methods: ['signMessage'],
        notifications: [],
      },
    },
  });

  const refreshed = await client.getSession();
  const scope = await resolveSolanaScope(client);
  const refreshedAccounts = refreshed?.sessionScopes?.[scope]?.accounts ?? [];

  return {
    scope,
    accounts: refreshedAccounts.map((entry) => caip10Address(entry)).filter(Boolean),
  };
}

export async function requestMetaMaskSolanaAccounts() {
  if (! await ensureMetaMaskMultichainRegistered()) {
    throw new Error('MetaMask Solana is not available on this page.');
  }

  if (!multichainClient) {
    throw new Error('MetaMask multichain client is not available on this page.');
  }

  const session = await ensureSolanaSession(multichainClient);
  if (!session.accounts.length) {
    throw new Error('MetaMask did not return a Solana account.');
  }

  return session.accounts;
}

export async function signMetaMaskBitcoinMessage(message, address) {
  if (!multichainClient) {
    await ensureMetaMaskMultichainRegistered();
  }

  if (!multichainClient) {
    throw new Error('MetaMask Bitcoin is not available on this page.');
  }

  const scope = await resolveBitcoinScope(multichainClient);
  const response = await multichainClient.invokeMethod({
    scope,
    request: {
      method: 'signMessage',
      params: {
        message,
        account: { address },
      },
    },
  });

  const signature = normalizeBitcoinSignature(response?.signature ?? response);
  if (!signature) {
    throw new Error('MetaMask returned an empty Bitcoin signature.');
  }

  return signature;
}

export async function signMetaMaskSolanaMessage(message, address) {
  if (!multichainClient) {
    await ensureMetaMaskMultichainRegistered();
  }

  if (!multichainClient) {
    throw new Error('MetaMask Solana is not available on this page.');
  }

  const session = await ensureSolanaSession(multichainClient);
  const scope = session.scope;
  const response = await multichainClient.invokeMethod({
    scope,
    request: {
      method: 'signMessage',
      params: {
        account: { address },
        message: encodeUtf8Base64(message),
      },
    },
  });

  const signature = normalizeSolanaSignature(response?.signature);
  if (!signature) {
    throw new Error('MetaMask returned an empty Solana signature.');
  }

  return {
    signature,
    signedMessage: response?.signedMessage || null,
  };
}

export function ensureMetaMaskBitcoinRegistered() {
  return ensureMetaMaskMultichainRegistered();
}

export function ensureMetaMaskMultichainRegistered() {
  if (typeof window === 'undefined') {
    return Promise.resolve(false);
  }

  if (registered) {
    return Promise.resolve(true);
  }

  if (!readMetaMaskEthereumProvider()) {
    return Promise.resolve(false);
  }

  if (!registrationPromise) {
    registrationPromise = (async () => {
      try {
        getWallets();

        const [
          { getMultichainClient, getDefaultTransport },
          { registerBitcoinWalletStandard },
        ] = await Promise.all([
          import('@metamask/multichain-api-client'),
          import('@metamask/bitcoin-wallet-standard'),
        ]);

        const client = getMultichainClient({ transport: getDefaultTransport() });
        multichainClient = client;
        await registerBitcoinWalletStandard({ client });
        registered = true;

        return true;
      } catch {
        multichainClient = null;
        return false;
      }
    })();
  }

  return registrationPromise;
}
