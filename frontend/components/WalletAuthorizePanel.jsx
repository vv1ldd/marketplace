'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';

const SCRIPT_SRC = 'https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js';

function hasIdentityHint(query) {
  return query.has('identity_hint') || query.has('login_hint') || query.has('entity_l1_address');
}

function loadWebAuthnBrowser() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Vault sign-in is not available here.'));
  }

  if (window.SimpleWebAuthnBrowser) {
    return Promise.resolve(window.SimpleWebAuthnBrowser);
  }

  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${SCRIPT_SRC}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve(window.SimpleWebAuthnBrowser), { once: true });
      existing.addEventListener('error', () => reject(new Error('Could not load Vault sign-in.')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = SCRIPT_SRC;
    script.async = true;
    script.onload = () => resolve(window.SimpleWebAuthnBrowser);
    script.onerror = () => reject(new Error('Could not load Vault sign-in.'));
    document.head.appendChild(script);
  });
}

function rememberIdentity(identity, payload = {}) {
  const identityHint = identity?.entity_l1_address || identity?.entityAddress || identity;
  if (!identityHint) {
    return;
  }

  try {
    window.localStorage?.setItem('sl1e.identity_hint', identityHint);
    if (payload.identity_capsule) {
      window.localStorage?.setItem('sl1e.identity_capsule', JSON.stringify(payload.identity_capsule));
    }
    if (payload.portability_contract) {
      window.localStorage?.setItem('sl1e.portability_contract', JSON.stringify(payload.portability_contract));
    }
  } catch {
    // Storage only improves continuity between product surfaces.
  }
}

function rememberedIdentity() {
  try {
    return window.localStorage?.getItem('sl1e.identity_hint') || '';
  } catch {
    return '';
  }
}

function forgetRememberedIdentity() {
  try {
    window.localStorage?.removeItem('sl1e.identity_hint');
    window.localStorage?.removeItem('sl1e.identity_capsule');
    window.localStorage?.removeItem('sl1e.portability_contract');
  } catch {
    // Forgetting only changes which local flow the browser suggests next.
  }
}

function queryWithBrowserHint(searchParams) {
  const query = new URLSearchParams(searchParams.toString());
  if (!hasIdentityHint(query)) {
    const remembered = rememberedIdentity();
    if (remembered) {
      query.set('browser_identity_hint', remembered);
    }
  }

  return query;
}

function normalizedAlias(value) {
  return String(value || '')
    .trim()
    .replace(/^@+/, '')
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .slice(0, 24);
}

function vaultErrorLabel(exception, fallback = 'Could not open Vault.') {
  const message = String(exception?.message || exception || '').trim();
  const lower = message.toLowerCase();

  if (!message) {
    return fallback;
  }

  if (
    lower.includes('notallowed')
    || lower.includes('not allowed')
    || lower.includes('timed out')
    || lower.includes('permission')
    || lower.includes('denied')
    || lower.includes('privacy-considerations-client')
    || lower.includes('user agent')
    || lower.includes('current context')
  ) {
    return 'Vault unlock was cancelled. Try again when you are ready.';
  }

  if (lower.includes('credential_not_bound_to_identity')) {
    return 'This key is not connected to this Vault. Choose another account or create a new Vault.';
  }

  if (lower.includes('authorization_challenge_not_found')) {
    return 'This unlock request expired. Open Vault again.';
  }

  if (lower.includes('credential_public_key_not_available')) {
    return 'This Vault key is not available on this device.';
  }

  return message;
}

function sameOriginPath(url) {
  try {
    const parsed = new URL(url, window.location.origin);
    if (parsed.origin !== window.location.origin) {
      return null;
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return null;
  }
}

function assertSl1eIdentityPayload(payload) {
  const identity = payload?.identity || {};
  const entityAddress = identity.entity_l1_address || identity.entityAddress;
  const keyAddress = identity.key_l1_address || identity.keyAddress;

  if (!String(entityAddress || '').startsWith('sl1e_') || !String(keyAddress || '').startsWith('sl1_')) {
    throw new Error('Vault did not return a valid identity key.');
  }
}

export function WalletAuthorizePanel() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [bootstrap, setBootstrap] = useState(null);
  const [alias, setAlias] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [forceCreateIdentity, setForceCreateIdentity] = useState(false);

  const queryKey = searchParams.toString();

  useEffect(() => {
    let cancelled = false;

    async function loadBootstrap() {
      setError('');

      try {
        const query = queryWithBrowserHint(searchParams);
        const response = await fetch(`/api/sl1e/authorize/bootstrap?${query.toString()}`, {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        const payload = await response.json();

        if (!response.ok) {
          throw new Error(vaultErrorLabel(payload.error, 'Could not open Vault.'));
        }

        if (cancelled) {
          return;
        }

        setBootstrap(payload);
        if (!forceCreateIdentity) {
          setAlias(normalizedAlias(payload.selected_identity?.display_alias || payload.selected_identity?.alias || ''));
        }
        if (!forceCreateIdentity && payload.selected_identity?.entity_l1_address) {
          rememberIdentity(payload.selected_identity.entity_l1_address);
        }
      } catch (exception) {
        if (!cancelled) {
          setError(vaultErrorLabel(exception));
        }
      }
    }

    loadBootstrap();

    return () => {
      cancelled = true;
    };
  }, [forceCreateIdentity, queryKey, searchParams]);

  const isRegister = forceCreateIdentity || (Boolean(bootstrap) && !bootstrap?.selected_identity);
  const primaryActionLabel = !bootstrap || isRegister ? 'Create identity' : 'Open Vault';

  const activeQuery = useMemo(() => queryWithBrowserHint(searchParams), [queryKey, searchParams]);

  async function approveAuthorize() {
    setBusy(true);
    setError('');

    try {
      if (!window.PublicKeyCredential) {
        throw new Error('Vault sign-in is not available in this browser.');
      }

      const webauthn = await loadWebAuthnBrowser();
      const query = new URLSearchParams(activeQuery.toString());
      query.delete('browser_identity_hint');
      query.delete('identity_hint');
      query.delete('login_hint');
      query.delete('entity_l1_address');

      const capsule = window.localStorage?.getItem('sl1e.identity_capsule');
      if (capsule && !query.has('identity_capsule')) {
        query.set('identity_capsule', capsule);
      }

      const optionsResponse = await fetch(`/api/sl1e/authentication/options?${query.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      const optionsPayload = await optionsResponse.json();
      if (!optionsResponse.ok) {
        throw new Error(vaultErrorLabel(optionsPayload.error, 'Could not prepare Vault sign-in.'));
      }

      const assertion = await webauthn.startAuthentication({ optionsJSON: optionsPayload.publicKey });

      const completeResponse = await fetch('/api/sl1e/authorize/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          authorization_request_id: optionsPayload.authorization_request_id,
          assertion,
        }),
      });
      const completePayload = await completeResponse.json();
      if (!completeResponse.ok) {
        throw new Error(vaultErrorLabel(completePayload.error, 'Could not open Vault.'));
      }

      assertSl1eIdentityPayload(completePayload);
      rememberIdentity(completePayload.identity, completePayload);
      if (!completePayload.redirect_url) {
        throw new Error('Vault did not return a destination.');
      }

      const localRedirect = sameOriginPath(completePayload.redirect_url);
      if (localRedirect) {
        router.push(localRedirect);
        return;
      }

      window.location.href = completePayload.redirect_url;
    } catch (exception) {
      setError(vaultErrorLabel(exception));
      setBusy(false);
    }
  }

  async function createAccount() {
    const username = normalizedAlias(alias);
    if (username.length < 3) {
      setError('Username must be at least 3 characters.');
      return;
    }

    setBusy(true);
    setError('');

    try {
      if (!window.PublicKeyCredential) {
        throw new Error('Vault sign-in is not available in this browser.');
      }

      const webauthn = await loadWebAuthnBrowser();
      const query = new URLSearchParams(searchParams.toString());
      query.delete('browser_identity_hint');
      query.delete('identity_hint');
      query.delete('login_hint');
      query.delete('entity_l1_address');
      query.delete('identity_capsule');
      query.set('alias', username);
      query.set('display_alias', username);

      const optionsResponse = await fetch(`/api/sl1e/registration/options?${query.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      const optionsPayload = await optionsResponse.json();
      if (!optionsResponse.ok) {
        throw new Error(optionsPayload.message || optionsPayload.error || 'Could not prepare account.');
      }

      const attestation = await webauthn.startRegistration({ optionsJSON: optionsPayload.publicKey });
      const completeResponse = await fetch('/api/sl1e/registration/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          registration_request_id: optionsPayload.registration_request_id,
          attestation,
        }),
      });
      const completePayload = await completeResponse.json();
      if (!completeResponse.ok) {
        throw new Error(vaultErrorLabel(completePayload.detail || completePayload.error, 'Could not create account.'));
      }

      assertSl1eIdentityPayload(completePayload);
      rememberIdentity(completePayload.identity, completePayload);
      if (!completePayload.redirect_url) {
        throw new Error('Vault did not return a destination.');
      }

      window.location.href = completePayload.redirect_url;
    } catch (exception) {
      setError(vaultErrorLabel(exception, 'Could not create account.'));
      setBusy(false);
    }
  }

  function startIdentityCreation() {
    forgetRememberedIdentity();
    setError('');
    setForceCreateIdentity(true);
    setAlias('');
  }

  return (
    <main className="page page--vault-authorize">
      <section className="vault-authorize-panel vault-authorize-panel--standalone">
        {isRegister ? (
          <label className="vault-authorize-field">
            <span>Username</span>
            <input
              autoComplete="username"
              maxLength={25}
              onChange={(event) => setAlias(event.target.value)}
              placeholder="@username"
              value={alias}
            />
            <small>Create identity first. Vault opens after this identity exists.</small>
          </label>
        ) : null}

        <div className="vault-authorize-primary-action">
          <button
            type="button"
            aria-label={primaryActionLabel}
            disabled={busy || !bootstrap}
            onClick={isRegister ? createAccount : approveAuthorize}
          >
            <span>{primaryActionLabel}</span>
          </button>
        </div>

        <div className="vault-authorize-secondary-actions">
          {!isRegister && bootstrap ? (
            <button type="button" disabled={busy} onClick={startIdentityCreation}>
              Create a new identity
            </button>
          ) : null}
        </div>

        <div className={`vault-authorize-message-slot ${error ? 'is-visible' : ''}`} aria-live="polite">
          {error ? <p className="product-card__reason">{error}</p> : <p className="checkout-note" aria-hidden="true">{'\u00a0'}</p>}
        </div>
      </section>
    </main>
  );
}
