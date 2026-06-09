'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useRef, useState } from 'react';
import {
  claimSimpleL1Handoff,
  fetchPremiumWalletAssets,
  fetchVault,
  logoutStorefrontSession,
  simpleL1ConnectUrl,
  vaultHandoffUrl,
} from '../lib/storefront-api';
import {
  clearVaultAuthorityState,
  rememberVaultBrowserPreference,
  readCachedVault,
  readStoredVaultToken,
  vaultAccessCookieName,
  writeCachedVault,
  writeStoredVaultToken,
} from '../lib/vault-authority';
import { GlossaryHint } from './GlossaryHint';
import { MeanlyConnectLink } from './MeanlyConnectLink';
import { VaultWalletContent } from './PremiumWalletPanel';

const DEFAULT_SCOPES = [
  'storefront:read',
  'storefront:checkout',
  'storefront:vault',
  'storefront:partner-registration',
];

function markVaultOpen() {
  try {
    document.cookie = `${vaultAccessCookieName}=open; Path=/; Max-Age=2592000; SameSite=Lax`;
  } catch {
    // The hint only improves first paint; Vault access still comes from the token/session.
  }
}

function persistToken(token) {
  writeStoredVaultToken(token);
  rememberVaultBrowserPreference();
  markVaultOpen();
}

function clearStoredToken() {
  clearVaultAuthorityState();
}

export function StorefrontSessionPanel({ claimHandoff = false, initialVault = null, initialVaultAccessState = 'checking' }) {
  const router = useRouter();
  const [vault, setVault] = useState(() => (
    initialVault
      || (initialVaultAccessState === 'open' && typeof window !== 'undefined'
        ? readCachedVault()
        : null)
  ));
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [wallet, setWallet] = useState(null);
  const [walletStatus, setWalletStatus] = useState('');
  const [walletError, setWalletError] = useState('');
  const [vaultAccessState, setVaultAccessState] = useState(() => (
    initialVault
      ? 'open'
      : initialVaultAccessState === 'open' && !vault ? 'checking' : initialVaultAccessState
  ));

  useEffect(() => {
    if (initialVault) {
      writeCachedVault(initialVault);
      markVaultOpen();
    }
  }, [initialVault]);

  /*
   * Keep hooks below the initial cache hydration. This lets logged-in users
   * see the completed Vault shell on first paint when SSR already resolved it.
   */
  const handoffClaimed = useRef(false);
  const browserConnectUrl = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentTitle: 'Open Meanly Vault',
    intentCta: 'Open Vault',
    intentDescription: 'Open your Vault.',
  });

  useEffect(() => {
    let isCancelled = false;

    if (claimHandoff) {
      return () => {
        isCancelled = true;
      };
    }

    async function resolveExistingVault() {
      const existingToken = readStoredVaultToken();
      let restored = false;

      if (existingToken) {
        setVaultAccessState((current) => (current === 'open' && vault ? 'open' : 'checking'));
      }

      if (existingToken) {
        restored = await loadVaultWith(existingToken);
      } else {
        restored = await restoreVaultFromLaravelSession({ silent: true });
      }

      if (!isCancelled && !restored) {
        setVaultAccessState('closed');
      }
    }

    resolveExistingVault();

    return () => {
      isCancelled = true;
    };
  }, [claimHandoff]);

  useEffect(() => {
    if (!claimHandoff || handoffClaimed.current) {
      return;
    }

    handoffClaimed.current = true;
    claimBackendHandoff();
  }, [claimHandoff]);

  async function claimBackendHandoff() {
    setError('');
    setStatus('Opening Meanly Vault...');
    setVault(null);
    setVaultAccessState('checking');

    try {
      const issued = await claimSimpleL1Handoff(DEFAULT_SCOPES);
      persistToken(issued.access_token);
      setStatus('');
      await loadVaultWith(issued.access_token);
      window.history.replaceState(null, '', '/vault');
    } catch (exception) {
      if (exception.status === 410) {
        setStatus('Vault approval expired. Open Vault again.');
        setVaultAccessState('closed');
      } else if (exception.status === 401) {
        setStatus('Approve in Meanly One to open Vault.');
        setVaultAccessState('closed');
      } else {
        setStatus('');
        setVaultAccessState('error');
      }

      setError(
        exception.message === 'Load failed'
          ? 'Vault could not reach Meanly. Open Vault again.'
          : exception.message,
      );
    }
  }

  async function restoreVaultFromLaravelSession({ silent = false } = {}) {
    if (!silent) {
      setStatus('Opening Meanly Vault...');
    }

    try {
      const issued = await claimSimpleL1Handoff(DEFAULT_SCOPES);
      persistToken(issued.access_token);
      setStatus('');
      return await loadVaultWith(issued.access_token, { allowSessionRefresh: false });
    } catch (exception) {
      if (silent && [401, 410].includes(exception.status)) {
        return false;
      }

      setVault(null);
      setVaultAccessState([401, 403, 410].includes(exception.status) ? 'closed' : 'error');
      setError(
        exception.message === 'Load failed'
          ? 'Vault could not reach Meanly. Open Vault again.'
          : exception.message,
      );
      return false;
    }
  }

  async function loadVaultWith(activeToken, { allowSessionRefresh = true } = {}) {
    if (!activeToken) {
      setVault(null);
      setWallet(null);
      setVaultAccessState('closed');
      return false;
    }

    try {
      setWalletError('');
      setWalletStatus('Loading Vault Wallet...');
      const payload = await fetchVault(activeToken);
      setVault(payload);
      writeCachedVault(payload);
      markVaultOpen();
      setVaultAccessState('open');
      loadWalletWith(activeToken);
      return true;
    } catch (exception) {
      if (exception.status === 403 && allowSessionRefresh) {
        const restored = await restoreVaultFromLaravelSession({ silent: true });
        if (restored) {
          return true;
        }
      }

      if ([401, 403].includes(exception.status)) {
        clearStoredToken();
        setVault(null);
        setWallet(null);
        setVaultAccessState('closed');
        setError('Vault is not open in this browser yet.');
        return false;
      }

      setVault(null);
      setWallet(null);
      setVaultAccessState('error');
      setError(exception.message || 'Vault is unavailable right now.');
      return false;
    }
  }

  async function loadWalletWith(activeToken) {
    try {
      const payload = await fetchPremiumWalletAssets(activeToken);
      setWallet(payload);
      setWalletStatus('');
      setWalletError('');
    } catch (exception) {
      setWallet(null);
      setWalletStatus('');
      setWalletError(exception.message || 'Vault Wallet is unavailable right now.');
    }
  }

  async function closeVaultAndSignOut() {
    setError('');
    setStatus('');
    clearStoredToken();
    setVault(null);
    setWallet(null);
    setWalletStatus('');
    setWalletError('');
    setVaultAccessState('closed');

    try {
      await logoutStorefrontSession();
      router.push('/');
    } catch (exception) {
      setError(
        exception.message === 'Load failed'
          ? 'Vault was closed locally, but Meanly could not reach logout.'
          : exception.message,
      );
    }
  }

  const isCheckingVault = vaultAccessState === 'checking' && !vault && !error;
  const isOpenVault = vaultAccessState === 'open' && vault;
  const isClosedVault = vaultAccessState === 'closed' && !isCheckingVault && !isOpenVault && !error;
  const shellStateClass = isOpenVault
    ? 'vault-shell--open'
    : isCheckingVault
      ? 'vault-shell--loading'
      : 'vault-shell--error';
  const statusLabel = isOpenVault ? 'Open' : isCheckingVault ? 'Vault' : 'Issue';
  const statusClassName = isOpenVault
    ? 'vault-status vault-status--open'
    : isCheckingVault
      ? 'vault-status vault-status--loading'
      : 'vault-status vault-status--locked';

  const shouldShowSignoutSignal = isOpenVault;

  return (
    <>
      {isClosedVault ? (
        <section className="vault-closed-action-panel">
          <div className="vault-authorize-primary-action">
            <MeanlyConnectLink
              href={browserConnectUrl}
              className="vault-closed-action-link"
              statusLabel="Opening Vault..."
              failureLabel="Could not open Vault. Try again."
            >
              <span className="vault-transition-logo" aria-hidden="true">
                <span />
                <span />
                <span />
              </span>
              <span>Open Vault</span>
            </MeanlyConnectLink>
          </div>

          {status ? <p className="checkout-note">{status}</p> : null}
          {error ? <p className="product-card__reason">{error}</p> : null}
        </section>
      ) : isOpenVault ? (
        <section className="premium-wallet-shell premium-wallet-shell--vault">
          <VaultWalletContent
            wallet={wallet}
            status={walletStatus}
            error={walletError}
            isVaultOpen
            showOpenAction={false}
          />
        </section>
      ) : (
        <section className={`vault-shell ${shellStateClass}`}>
          <div className="vault-shell__header vault-shell__header--stable">
            <div className="vault-status-slot">
              <span className={statusClassName}>
                {statusLabel}
                {!isOpenVault ? (
                  <GlossaryHint>Your protected place for saved items, orders, and account access.</GlossaryHint>
                ) : null}
              </span>
            </div>
            <div className="vault-identity-card vault-identity-card--placeholder" aria-hidden="true">
              <span>Identity</span>
              <strong>Loading</strong>
            </div>
          </div>

          <div className="vault-content-slot">
            {isCheckingVault ? (
              <section className="vault-transition-state" aria-live="polite">
                <span className="vault-transition-logo vault-transition-logo--large" aria-hidden="true">
                  <span />
                  <span />
                  <span />
                </span>
                <strong>Opening Vault</strong>
              </section>
            ) : (
              <section className="vault-empty-state vault-empty-state--error">
                <span>Vault</span>
                <strong>Could not open Vault.</strong>
                <p>Try opening Vault again.</p>
              </section>
            )}
          </div>

          <div className={`vault-message-slot ${status || error ? 'is-visible' : ''}`}>
            {error ? <p className="product-card__reason">{error}</p> : <p className="checkout-note">{status || '\u00a0'}</p>}
          </div>
        </section>
      )}

      {!isClosedVault ? (
        <div className={`vault-signout-link-row ${shouldShowSignoutSignal ? 'is-visible' : ''}`}>
          {shouldShowSignoutSignal ? (
            <button className="vault-signout-signal__action" type="button" onClick={closeVaultAndSignOut}>
              Sign out
            </button>
          ) : (
            <span aria-hidden="true">Sign out</span>
          )}
        </div>
      ) : null}

    </>
  );
}
