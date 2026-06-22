import { TonConnectUI } from '@tonconnect/ui';
import { WalletAlreadyConnectedError } from '@tonconnect/sdk';
import { WalletConnectError } from './wallet-connect-error';

let tonConnectUi = null;
let pendingConnection = null;

function getTonConnectUi() {
  if (typeof window === 'undefined') {
    return null;
  }

  if (!tonConnectUi) {
    tonConnectUi = new TonConnectUI({
      manifestUrl: `${window.location.origin}/tonconnect-manifest.json`,
      actionsConfiguration: {
        twaReturnUrl: `${window.location.origin}/vault`,
      },
    });
  }

  return tonConnectUi;
}

async function waitForTonConnection(ui) {
  if (ui?.connectionRestored && typeof ui.connectionRestored.then === 'function') {
    await ui.connectionRestored;
  }
}

function readTonAccount(ui, { requirePublicKey = false } = {}) {
  const account = ui?.account || ui?.wallet?.account;
  const address = account?.address;
  const publicKey = account?.publicKey;

  if (!address) {
    return null;
  }

  if (requirePublicKey && !publicKey) {
    return null;
  }

  return {
    address,
    publicKey: publicKey || null,
  };
}

async function waitForTonAccount(ui, options = {}, timeoutMs = 12000) {
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    const account = readTonAccount(ui, options);
    if (account && (!options.requirePublicKey || account.publicKey)) {
      return account;
    }

    if (!ui.connected) {
      break;
    }

    await new Promise((resolve) => {
      window.setTimeout(resolve, 100);
    });
  }

  return readTonAccount(ui, options);
}

function isWalletAlreadyConnectedError(error) {
  return error instanceof WalletAlreadyConnectedError
    || String(error?.message || error).includes('WalletAlreadyConnectedError');
}

async function openTonConnectModal(ui) {
  if (ui.connected) {
    const account = await waitForTonAccount(ui);
    if (account) {
      return account;
    }

    await ui.disconnect();
    await waitForTonConnection(ui);
  }

  try {
    await ui.openModal();
  } catch (error) {
    if (isWalletAlreadyConnectedError(error)) {
      const account = await waitForTonAccount(ui);
      if (account) {
        return account;
      }
    }

    throw error;
  }

  await waitForTonConnection(ui);
  const account = await waitForTonAccount(ui);
  if (!account) {
    throw new WalletConnectError('no_account', 'No TON account was returned.');
  }

  return account;
}

async function ensureTonConnection(ui) {
  await waitForTonConnection(ui);

  let account = await waitForTonAccount(ui);
  if (account) {
    return account;
  }

  if (!pendingConnection) {
    pendingConnection = openTonConnectModal(ui).finally(() => {
      pendingConnection = null;
    });
  }

  return pendingConnection;
}

async function ensureTonAccountForSigning(ui) {
  const account = await ensureTonConnection(ui);

  if (account.publicKey) {
    return account;
  }

  const withPublicKey = await waitForTonAccount(ui, { requirePublicKey: true }, 5000);
  if (withPublicKey?.publicKey) {
    return withPublicKey;
  }

  throw new WalletConnectError(
    'wallet_error',
    'Connected TON wallet did not expose a public key. Reconnect in Tonkeeper and try again.',
  );
}

export async function resolveTonConnect() {
  const ui = getTonConnectUi();
  if (!ui) {
    return null;
  }

  return {
    id: 'tonconnect',
    requestAccounts: async () => {
      const account = await ensureTonConnection(ui);
      return [account.address];
    },
    signMessage: async (message, address) => {
      const account = await ensureTonAccountForSigning(ui);

      if (address && account.address !== address) {
        throw new WalletConnectError(
          'wallet_error',
          'Connected TON wallet does not match the requested address.',
        );
      }

      const result = await ui.signData({
        type: 'text',
        text: message,
      });

      const signature = result?.signature;
      if (!signature) {
        throw new WalletConnectError(
          'wallet_error',
          'TON wallet returned an empty ownership signature.',
        );
      }

      return {
        signature,
        walletPublicKey: account.publicKey,
        tonSignData: {
          domain: result.domain,
          timestamp: result.timestamp,
          address: result.address,
          payload: result.payload,
        },
      };
    },
  };
}
