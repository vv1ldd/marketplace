'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  claimSimpleL1Handoff,
  enrichWalletBundleAssets,
  fetchWalletCoreBundle,
  fetchVault,
  mergeWalletCoreUpdate,
  logoutStorefrontSession,
  vaultHandoffUrl,
  VAULT_STOREFRONT_SCOPES,
} from '../lib/storefront-api';
import { navigateToVaultEntry } from '../lib/vault-entry';
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
import { MeanlyLoadingMark } from './MeanlyLoadingMark';
import { useLocale } from './LocaleProvider';
import { VaultWalletContent } from './PremiumWalletPanel';

const DEFAULT_SCOPES = VAULT_STOREFRONT_SCOPES;

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

export function StorefrontSessionPanel({
  claimHandoff = false,
  initialAccessToken = null,
  initialVault = null,
  initialWallet = null,
  initialVaultAccessState = 'checking',
}) {
  const router = useRouter();
  const { t } = useLocale();
  const [vault, setVault] = useState(() => (
    initialVault
      || (initialVaultAccessState === 'open' && typeof window !== 'undefined'
        ? readCachedVault()
        : null)
  ));
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [wallet, setWallet] = useState(() => initialWallet);
  const walletRef = useRef(initialWallet);
  const [walletStatus, setWalletStatus] = useState('');
  const [walletError, setWalletError] = useState('');
  const [isSigningOut, setIsSigningOut] = useState(false);
  const [vaultAccessState, setVaultAccessState] = useState(() => (
    initialVault
      ? 'open'
      : initialVaultAccessState === 'open' && !vault ? 'checking' : initialVaultAccessState
  ));

  useEffect(() => {
    if (initialAccessToken) {
      persistToken(initialAccessToken);
    }

    if (initialVault) {
      writeCachedVault(initialVault);
      markVaultOpen();
    }
  }, [initialAccessToken, initialVault]);

  /*
   * Keep hooks below the initial cache hydration. This lets logged-in users
   * see the completed Vault shell on first paint when SSR already resolved it.
   */
  const handoffClaimed = useRef(false);
  const vaultRedirectStarted = useRef(false);
  const signOutStarted = useRef(false);

  useEffect(() => {
    walletRef.current = wallet;
  }, [wallet]);

  useEffect(() => {
    if (claimHandoff) {
      return () => {};
    }

    if (initialVault) {
      let isCancelled = false;

      async function hydrateInitialVault() {
        if (initialAccessToken) {
          persistToken(initialAccessToken);
        } else {
          await restoreVaultFromLaravelSession({ silent: true });
        }

        if (!isCancelled && initialWallet) {
          const activeToken = readStoredVaultToken();
          if (activeToken) {
            enrichWalletBundleAssets(activeToken, initialWallet)
              .then((enriched) => {
                if (!isCancelled) {
                  setWallet((current) => enriched || current);
                }
              })
              .catch(() => {});
          }
        } else if (!isCancelled && !initialWallet) {
          const activeToken = readStoredVaultToken();
          if (activeToken) {
            await loadWalletWith(activeToken, { silent: true });
          }
        }
      }

      hydrateInitialVault();

      return () => {
        isCancelled = true;
      };
    }

    let isCancelled = false;

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
  }, [claimHandoff, initialAccessToken, initialVault, initialWallet]);

  useEffect(() => {
    if (!claimHandoff || handoffClaimed.current) {
      return;
    }

    handoffClaimed.current = true;
    claimBackendHandoff();
  }, [claimHandoff]);

  useEffect(() => {
    if (claimHandoff || signOutStarted.current || vaultAccessState !== 'closed' || error || vaultRedirectStarted.current) {
      return;
    }

    vaultRedirectStarted.current = true;
    navigateToVaultEntry(router, {
      returnTo: vaultHandoffUrl({ return_to: '/vault' }),
      intentTitle: t('header_connect_title'),
      intentCta: t('intent_cta'),
      intentDescription: t('header_connect_description'),
    }).catch(() => {
      vaultRedirectStarted.current = false;
      setVaultAccessState('closed');
      setError(t('sl1_connect_load_error'));
    });
  }, [claimHandoff, error, router, t, vaultAccessState]);

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

  async function loadVaultWith(activeToken, { allowSessionRefresh = true, refreshWallet = true } = {}) {
    if (!activeToken) {
      setVault(null);
      setWallet(null);
      setVaultAccessState('closed');
      return false;
    }

    try {
      setWalletError('');
      const payload = await fetchVault(activeToken);
      setVault(payload);
      writeCachedVault(payload);
      markVaultOpen();
      setVaultAccessState('open');
      if (refreshWallet) {
        loadWalletWith(activeToken, { silent: true });
      }
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

  async function loadWalletWith(activeToken, { silent = false, includeAssets = true } = {}) {
    try {
      if (!silent) {
        setWalletStatus('Loading Vault Wallet...');
      }

      const core = await fetchWalletCoreBundle(activeToken);
      setWallet(core);
      setWalletStatus('');
      setWalletError('');

      if (includeAssets) {
        enrichWalletBundleAssets(activeToken, core)
          .then((enriched) => {
            setWallet((current) => enriched || current);
          })
          .catch(() => {});
      }
    } catch (exception) {
      setWallet(null);
      setWalletStatus('');
      setWalletError(exception.message || 'Vault Wallet is unavailable right now.');
    }
  }

  const refreshVaultWallet = useCallback(async () => {
    const activeToken = readStoredVaultToken();
    if (!activeToken) {
      return;
    }

    const core = await fetchWalletCoreBundle(activeToken);
    setWallet((current) => mergeWalletCoreUpdate(current, core));
  }, []);

  const refreshVaultWalletAssets = useCallback(async () => {
    const activeToken = readStoredVaultToken();
    if (!activeToken) {
      return;
    }

    const current = walletRef.current;
    if (!current) {
      await loadWalletWith(activeToken, { silent: true, includeAssets: true });
      return;
    }

    const enriched = await enrichWalletBundleAssets(activeToken, current);
    if (enriched) {
      setWallet(enriched);
    }
  }, []);

  async function closeVaultAndSignOut() {
    if (signOutStarted.current) {
      return;
    }

    signOutStarted.current = true;
    vaultRedirectStarted.current = true;
    setIsSigningOut(true);
    setError('');
    setStatus('');

    clearStoredToken();

    try {
      await logoutStorefrontSession();
    } catch (exception) {
      if (exception.message !== 'Load failed') {
        console.warn(exception.message);
      }
    }

    window.location.assign('/');
  }

  const isHydratingVault = vaultAccessState === 'checking' && !vault && !error;
  const isCheckingVault = isHydratingVault;
  const isOpenVault = vaultAccessState === 'open' && vault;
  const isClosedVault = vaultAccessState === 'closed' && !isCheckingVault && !isOpenVault && !error;
  const showVaultWorkspace = Boolean(vault) && !error && (isOpenVault || vaultAccessState === 'checking');
  const isWalletBootstrapping = showVaultWorkspace && !wallet && !walletError && !walletStatus;
  const shellStateClass = 'vault-shell--error';
  const statusLabel = 'Issue';
  const statusClassName = 'vault-status vault-status--locked';

  const shouldShowSignoutSignal = isOpenVault || Boolean(initialVault);
  const vaultStatusNote = isHydratingVault
    ? (status || t('wallet_shell_loading'))
    : walletStatus || (isWalletBootstrapping ? t('wallet_vault_opening') : '');

  return (
    <>
      {isHydratingVault ? (
        <section className="vault-transition-state vault-transition-state--panel" aria-live="polite">
          <MeanlyLoadingMark label={vaultStatusNote} size="lg" />
        </section>
      ) : isClosedVault ? (
        <section className="vault-transition-state" aria-live="polite">
          <MeanlyLoadingMark label={t('header_connect_title')} size="md" />
        </section>
      ) : showVaultWorkspace ? (
        <>
          <div className="vault-workspace vault-workspace--adaptive">
            <section className={`premium-wallet-shell premium-wallet-shell--vault premium-wallet-shell--compact ${isWalletBootstrapping ? 'is-bootstrapping' : ''}`}>
              <VaultWalletContent
                error={walletError}
                isLoading={isWalletBootstrapping}
                isVaultOpen
                onRefreshWallet={refreshVaultWallet}
                onRefreshWalletAssets={refreshVaultWalletAssets}
                showOpenAction={false}
                status={vaultStatusNote}
                variant="vault"
                vaultIdentity={vault?.identity ?? null}
                wallet={wallet}
              />
            </section>
          </div>

          <div className={`vault-signout-shell ${shouldShowSignoutSignal ? 'is-visible' : ''}`}>
            {shouldShowSignoutSignal ? (
              <button
                className="meanly-pill-button"
                disabled={isSigningOut}
                type="button"
                onClick={closeVaultAndSignOut}
              >
                <span className="meanly-pill-button__mark" aria-hidden="true" />
                {t('vault_sign_out')}
              </button>
            ) : null}
          </div>
        </>
      ) : (
        <section className={`vault-shell ${shellStateClass}`}>
          <div className="vault-shell__header vault-shell__header--stable">
            <div className="vault-status-slot">
              <span className={statusClassName}>
                {statusLabel}
                <GlossaryHint>Your protected place for saved items, orders, and account access.</GlossaryHint>
              </span>
            </div>
            <div className="vault-identity-card vault-identity-card--placeholder" aria-hidden="true">
              <span>Identity</span>
              <strong>Loading</strong>
            </div>
          </div>

          <div className="vault-content-slot">
            <section className="vault-empty-state vault-empty-state--error">
              <span>Vault</span>
              <strong>Could not open Vault.</strong>
              <p>Try opening Vault again.</p>
            </section>
          </div>

          <div className={`vault-message-slot ${status || error ? 'is-visible' : ''}`}>
            {error ? <p className="product-card__reason">{error}</p> : <p className="checkout-note">{status || '\u00a0'}</p>}
          </div>
        </section>
      )}

    </>
  );
}
