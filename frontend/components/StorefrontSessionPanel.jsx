'use client';

import { useEffect, useRef, useState } from 'react';
import {
  claimSimpleL1Handoff,
  fetchVault,
  simpleL1ConnectUrl,
  vaultHandoffUrl,
} from '../lib/storefront-api';
import { MeanlyConnectLink } from './MeanlyConnectLink';

const TOKEN_STORAGE_KEY = 'meanly:storefront-token';
const VAULT_ACCESS_COOKIE = 'meanly_vault_access';
const VAULT_CACHE_KEY = 'meanly:vault-cache';
const DEFAULT_SCOPES = [
  'storefront:read',
  'storefront:checkout',
  'storefront:vault',
  'storefront:partner-registration',
];

function storedToken() {
  try {
    return window.localStorage.getItem(TOKEN_STORAGE_KEY) || '';
  } catch {
    return '';
  }
}

function cachedVault() {
  try {
    return JSON.parse(window.sessionStorage.getItem(VAULT_CACHE_KEY) || 'null');
  } catch {
    return null;
  }
}

function cacheVault(vault) {
  try {
    window.sessionStorage.setItem(VAULT_CACHE_KEY, JSON.stringify(vault));
  } catch {
    // The cache is only used to avoid a duplicate loading state between routes.
  }
}

function markVaultOpen() {
  try {
    document.cookie = `${VAULT_ACCESS_COOKIE}=open; Path=/; Max-Age=2592000; SameSite=Lax`;
  } catch {
    // The hint only improves first paint; Vault access still comes from the token/session.
  }
}

function persistToken(token) {
  try {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
    markVaultOpen();
  } catch {
    // Browser storage is a convenience only; the backend token remains authoritative.
  }
}

function clearStoredToken() {
  try {
    window.localStorage.removeItem(TOKEN_STORAGE_KEY);
    window.sessionStorage.removeItem(VAULT_CACHE_KEY);
    document.cookie = `${VAULT_ACCESS_COOKIE}=; Path=/; Max-Age=0; SameSite=Lax`;
  } catch {
    // Ignore storage failures; the backend session is still authoritative.
  }
}

export function StorefrontSessionPanel({ claimHandoff = false, initialVault = null, initialVaultAccessState = 'checking' }) {
  const [vault, setVault] = useState(() => (
    initialVault
      || (initialVaultAccessState === 'open' && typeof window !== 'undefined'
        ? cachedVault()
        : null)
  ));
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [vaultAccessState, setVaultAccessState] = useState(() => (
    initialVault
      ? 'open'
      : initialVaultAccessState === 'open' && !vault ? 'checking' : initialVaultAccessState
  ));

  useEffect(() => {
    if (initialVault) {
      cacheVault(initialVault);
      markVaultOpen();
    }
  }, [initialVault]);

  /*
   * Keep hooks below the initial cache hydration. This lets logged-in users
   * see the completed Vault shell on first paint when SSR already resolved it.
   */
  const handoffClaimed = useRef(false);
  const authoritySurfaces = Array.isArray(vault?.authority_surfaces) ? vault.authority_surfaces : [];
  const vaultItems = Array.isArray(vault?.items) ? vault.items : [];
  const displayIdentity = vault?.identity?.display_alias
    || vault?.identity?.alias
    || vault?.identity?.entity_l1_address
    || 'Connected identity';
  const connectUrl = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentTitle: 'Open Meanly Vault',
    intentCta: 'Open Vault',
    intentDescription: 'Sign in to open purchases, receipts, safe codes, and saved products.',
  });

  useEffect(() => {
    let isCancelled = false;

    if (claimHandoff) {
      return () => {
        isCancelled = true;
      };
    }

    async function resolveExistingVault() {
      const existingToken = storedToken();
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
      setStatus('Meanly Vault opened.');
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
      setStatus('Meanly Vault opened.');
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
      setVaultAccessState('closed');
      return false;
    }

    try {
      const payload = await fetchVault(activeToken);
      setVault(payload);
      cacheVault(payload);
      markVaultOpen();
      setVaultAccessState('open');
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
        setVaultAccessState('closed');
        setError('Vault is not open in this browser yet.');
        return false;
      }

      setVault(null);
      setVaultAccessState('error');
      setError(exception.message || 'Vault is unavailable right now.');
      return false;
    }
  }

  const isCheckingVault = vaultAccessState === 'checking' && !vault && !error;
  const shellStateClass = vaultAccessState === 'open'
    ? 'vault-shell--open'
    : isCheckingVault
      ? 'vault-shell--loading'
      : 'vault-shell--locked';

  return (
    <section className={`vault-shell ${shellStateClass}`}>
      {vaultAccessState === 'open' && vault ? (
        <>
          <div className="vault-shell__header">
            <div>
              <span className="vault-status vault-status--open">Vault open</span>
              <h2>Your Vault is open.</h2>
              <p>
                Your purchases, safe codes, saved products, and receipts will
                appear here.
              </p>
            </div>
            <div className="vault-identity-card">
              <span>Identity</span>
              <strong>{displayIdentity}</strong>
            </div>
          </div>

          {vaultItems.length ? (
            <div className="vault-items-grid">
              {vaultItems.map((item) => (
                <article className="vault-item-card" key={item.order_uuid || item.order_id}>
                  <span>{item.type || 'vault_item'}</span>
                  <strong>{item.product_name || item.order_id || 'Vault item'}</strong>
                  <p>
                    Order {item.order_id || item.order_uuid}. Safe status is ready.
                  </p>
                </article>
              ))}
            </div>
          ) : (
            <section className="vault-empty-state">
              <span>Saved items</span>
              <strong>No saved items yet.</strong>
              <p>
                Purchases, receipts, saved products, and safe codes will appear
                here after your first order or save.
              </p>
            </section>
          )}

          {authoritySurfaces.length ? (
            <section className="vault-workspace-panel">
              <div>
                <span className="vault-status">Available tools</span>
                <h3>Open your tools.</h3>
                <p>
                  Extra tools appear here when they are available for your account.
                </p>
              </div>
              <div className="vault-workspace-grid">
                {authoritySurfaces.map((surface) => (
                  <a href={surface.href} key={surface.key}>
                    <span>{surface.grant}</span>
                    <strong>{surface.label}</strong>
                    <p>{surface.description}</p>
                  </a>
                ))}
              </div>
            </section>
          ) : null}
        </>
      ) : isCheckingVault ? (
        <>
          <div className="vault-shell__header">
            <div>
              <span className="vault-status vault-status--loading">Vault</span>
              <h2>Your Vault.</h2>
              <p>
                Checking your sign-in and loading saved items.
              </p>
            </div>
            <div className="vault-step-list">
              <span>Checking sign-in</span>
              <span>Loading Vault</span>
              <span>Loading saved items</span>
            </div>
          </div>
          <section className="vault-empty-state vault-empty-state--loading">
            <span>Saved items</span>
            <strong>Loading your Vault.</strong>
            <p>
              Purchases, receipts, saved products, and safe codes will appear here.
            </p>
          </section>
        </>
      ) : (
        <>
          <div className="vault-shell__header">
            <div>
              <span className="vault-status vault-status--locked">Vault locked</span>
              <h2>Open your Vault.</h2>
              <p>
                Sign in with Meanly to see purchases, receipts, safe codes, and
                saved products in one place.
              </p>
            </div>
            <div className="vault-step-list">
              <span>Sign in with Meanly</span>
              <span>Return to your Vault</span>
              <span>View saved items</span>
            </div>
          </div>

          <div className="vault-action-row">
            <MeanlyConnectLink
              href={connectUrl}
              className="vault-open-button"
              statusLabel="Opening Vault in Meanly One."
              unavailableLabel="Meanly One app is not available here. Open Vault in browser."
              failureLabel="Vault cannot open the app here. Open Vault in browser."
              onlineLabel="Open in browser"
            >
              Open Vault
            </MeanlyConnectLink>
          </div>

          {status ? <p className="checkout-note">{status}</p> : null}
          {error ? <p className="product-card__reason">{error}</p> : null}
        </>
      )}
    </section>
  );
}
