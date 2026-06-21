import {
  CONNECTABLE_WALLET_KEYS,
} from './identity-wallets';
import { readMetaMaskEthereumProvider } from './metamask-multichain-bootstrap';
import { resolveBitcoinConnect } from './bitcoin-wallet-connect';
import { resolveSolanaConnect } from './solana-wallet-connect';
import { WalletConnectError } from './wallet-connect-error';
import {
  requestWalletBindingChallenge,
  verifyWalletBindingChallenge,
} from './storefront-api';

const EVM_NETWORKS = {
  polygon: {
    chainId: '0x89',
    chainName: 'Polygon Mainnet',
    nativeCurrency: { name: 'POL', symbol: 'POL', decimals: 18 },
    rpcUrls: ['https://polygon-rpc.com'],
    blockExplorerUrls: ['https://polygonscan.com'],
    wrongNetworkMessage: 'Switch your wallet to Polygon, then try Connect again.',
  },
  ethereum: {
    chainId: '0x1',
    chainName: 'Ethereum Mainnet',
    nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
    rpcUrls: ['https://cloudflare-eth.com'],
    blockExplorerUrls: ['https://etherscan.io'],
    wrongNetworkMessage: 'Switch your wallet to Ethereum mainnet, then try Connect again.',
  },
  base: {
    chainId: '0x2105',
    chainName: 'Base',
    nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
    rpcUrls: ['https://mainnet.base.org'],
    blockExplorerUrls: ['https://basescan.org'],
    wrongNetworkMessage: 'Switch your wallet to Base, then try Connect again.',
  },
};

export { WalletConnectError } from './wallet-connect-error';

function readEthereumProvider() {
  return readMetaMaskEthereumProvider();
}

function mapVerifyError(error) {
  if (error?.status === 401) {
    return new WalletConnectError(
      'session_expired',
      'Vault session expired. Refresh the page and try Connect again.',
      error,
    );
  }

  const field = error?.payload?.errors
    ? Object.keys(error.payload.errors)[0]
    : null;
  const detail = field ? error.payload.errors[field]?.[0] : null;

  if (field === 'nonce') {
    return new WalletConnectError(
      'challenge_expired',
      'This connection request expired. Tap Connect and try again.',
      error,
    );
  }

  if (field === 'signature') {
    const detailText = String(detail || '');
    if (detailText.includes('verification is unavailable on this server')) {
      return new WalletConnectError(
        'verifier_unavailable',
        detailText,
        error,
      );
    }

    return new WalletConnectError(
      'signature_rejected',
      'We could not confirm wallet ownership. Check the wallet you opened and try again.',
      error,
    );
  }

  if (field === 'binding_value') {
    return new WalletConnectError(
      'address_in_use',
      'This wallet address is already linked to another Vault identity.',
      error,
    );
  }

  return new WalletConnectError(
    'verify_failed',
    detail || error?.message || 'Wallet connection could not be completed.',
    error,
  );
}

function mapProviderError(error) {
  if (error?.code === 4001) {
    return new WalletConnectError(
      'user_rejected',
      'Wallet connection was cancelled.',
      error,
    );
  }

  if (error?.code === -32002) {
    return new WalletConnectError(
      'wallet_busy',
      'Your wallet is already waiting for a response. Open it and finish the pending request.',
      error,
    );
  }

  return new WalletConnectError(
    'wallet_error',
    error?.message || 'Could not open your wallet.',
    error,
  );
}

async function ensureEvmNetwork(provider, bindingKey) {
  const network = EVM_NETWORKS[bindingKey];
  if (!network) {
    throw new WalletConnectError('unsupported_wallet', 'This EVM network is not ready to connect yet.');
  }

  const chainId = await provider.request({ method: 'eth_chainId' });
  if (chainId?.toLowerCase() === network.chainId) {
    return;
  }

  try {
    await provider.request({
      method: 'wallet_switchEthereumChain',
      params: [{ chainId: network.chainId }],
    });
    return;
  } catch (error) {
    if (error?.code !== 4902) {
      throw mapProviderError(error);
    }
  }

  try {
    await provider.request({
      method: 'wallet_addEthereumChain',
      params: [{
        chainId: network.chainId,
        chainName: network.chainName,
        nativeCurrency: network.nativeCurrency,
        rpcUrls: network.rpcUrls,
        blockExplorerUrls: network.blockExplorerUrls,
      }],
    });
  } catch (error) {
    throw new WalletConnectError(
      'wrong_network',
      network.wrongNetworkMessage,
      error,
    );
  }
}

async function completeBindingChallenge({ token, bindingKey, address, sign }) {
  let challenge;
  try {
    const response = await requestWalletBindingChallenge(token, {
      binding_type: 'wallet',
      binding_key: bindingKey,
      binding_value: address,
      verification_method: 'signature',
    });
    challenge = response.challenge;
  } catch (error) {
    throw mapVerifyError(error);
  }

  let signaturePayload;
  try {
    signaturePayload = await sign(challenge.message, address);
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  const signature = typeof signaturePayload === 'object' && signaturePayload !== null
    ? signaturePayload.signature
    : signaturePayload;
  const signedMessage = typeof signaturePayload === 'object' && signaturePayload !== null
    ? signaturePayload.signedMessage || undefined
    : undefined;

  try {
    await verifyWalletBindingChallenge(token, {
      nonce: challenge.nonce,
      signature,
      signed_message: signedMessage,
    });
  } catch (error) {
    throw mapVerifyError(error);
  }

  return { bindingKey, address };
}

/**
 * @param {{ token: string, bindingKey: string }} options
 */
export async function connectWalletBinding({ token, bindingKey }) {
  if (!CONNECTABLE_WALLET_KEYS.has(bindingKey)) {
    throw new WalletConnectError('unsupported_wallet', 'This wallet is not ready to connect yet.');
  }

  if (bindingKey === 'bitcoin') {
    return connectBitcoinBinding({ token, bindingKey });
  }

  if (bindingKey === 'solana') {
    return connectSolanaBinding({ token, bindingKey });
  }

  return connectEvmWalletBinding({ token, bindingKey });
}

async function connectEvmWalletBinding({ token, bindingKey }) {
  const provider = readEthereumProvider();
  if (!provider) {
    throw new WalletConnectError(
      'no_wallet',
      'Install MetaMask, then try Connect again.',
    );
  }

  let accounts;
  try {
    accounts = await provider.request({ method: 'eth_requestAccounts' });
  } catch (error) {
    throw mapProviderError(error);
  }

  const address = accounts?.[0];
  if (!address) {
    throw new WalletConnectError('no_account', 'No wallet account was returned.');
  }

  try {
    await ensureEvmNetwork(provider, bindingKey);
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  return completeBindingChallenge({
    token,
    bindingKey,
    address,
    sign: async (message, account) => provider.request({
      method: 'personal_sign',
      params: [message, account],
    }),
  });
}

async function connectBitcoinBinding({ token, bindingKey }) {
  let connect;
  try {
    connect = await resolveBitcoinConnect();
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  if (!connect) {
    throw new WalletConnectError(
      'no_wallet',
      'Install MetaMask or a Bitcoin wallet such as Unisat or Xverse, then try Connect again.',
    );
  }

  let accounts;
  try {
    accounts = await connect.requestAccounts();
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  const address = accounts?.[0];
  if (!address) {
    throw new WalletConnectError('no_account', 'No Bitcoin address was returned.');
  }

  return completeBindingChallenge({
    token,
    bindingKey,
    address,
    sign: async (message, account) => connect.signMessage(message, account),
  });
}

async function connectSolanaBinding({ token, bindingKey }) {
  let connect;
  try {
    connect = await resolveSolanaConnect();
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  if (!connect) {
    throw new WalletConnectError(
      'no_wallet',
      'Install MetaMask (Solana account) or Phantom, then try Connect again.',
    );
  }

  let accounts;
  try {
    accounts = await connect.requestAccounts();
  } catch (error) {
    if (error instanceof WalletConnectError) {
      throw error;
    }
    throw mapProviderError(error);
  }

  const address = accounts?.[0];
  if (!address) {
    throw new WalletConnectError('no_account', 'No Solana account was returned.');
  }

  return completeBindingChallenge({
    token,
    bindingKey,
    address,
    sign: async (message, account) => connect.signMessage(message, account),
  });
}
