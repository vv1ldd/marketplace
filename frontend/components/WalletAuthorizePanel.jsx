'use client';

import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { isPrimaryMobileDevice, supportsCrossDeviceHandoff } from '../lib/device-context';
import { ensureHandoffQrDataUrl } from '../lib/handoff-qr';
import { useLocale } from './LocaleProvider';
import { IdentityStateStage, IdentityStatusSlot } from './IdentityStateStage';
import { VaultKeyIcon, VaultPhoneIcon, VaultShieldIcon } from './IdentityVaultIcons';
import { buildAuthorizeParams, buildSl1eAuthorizePayload } from '../lib/vault-authorize-params';
import { VaultUsernameField } from './VaultUsernameField';

const SCRIPT_SRC = 'https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js';

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

function readVaultHint() {
  try {
    return window.localStorage?.getItem('vault_identity_hint')
      || window.localStorage?.getItem('sl1e.identity_hint')
      || '';
  } catch {
    return '';
  }
}

function rememberVaultHint(entityAddress) {
  if (!entityAddress) {
    return;
  }

  try {
    window.localStorage?.setItem('vault_identity_hint', entityAddress);
    window.localStorage?.setItem('sl1e.identity_hint', entityAddress);
  } catch {
    // Storage only improves continuity between product surfaces.
  }
}

function forgetVaultHint() {
  try {
    window.localStorage?.removeItem('vault_identity_hint');
    window.localStorage?.removeItem('sl1e.identity_hint');
    window.localStorage?.removeItem('sl1e.identity_capsule');
    window.localStorage?.removeItem('sl1e.portability_contract');
  } catch {
    // Forgetting only changes which local flow the browser suggests next.
  }
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

  if (lower.includes('sl1e identity or key is not registered')) {
    return 'Saved Vault was not found. Starting discovery instead.';
  }

  if (lower.includes('authorization flow not found')) {
    return 'This unlock request expired. Open Vault again.';
  }

  if (lower.includes('credential not found in identity stream projection')) {
    return 'This device key is not linked to a Safe here. Create a new Safe on this storefront.';
  }

  if (lower.includes('identity flow must stay on the same storefront region')) {
    return 'This unlock link belongs to another storefront region. Open Vault again from this site.';
  }

  return message;
}

async function postSl1eJson(path, body) {
  const response = await fetch(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
    cache: 'no-store',
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok || payload.success === false) {
    const error = new Error(payload.message || payload.error || 'Request failed');
    error.status = response.status;
    throw error;
  }

  return payload;
}

function followRedirect(redirectUrl) {
  if (!redirectUrl) {
    throw new Error('Vault did not return a destination.');
  }

  window.location.href = redirectUrl;
}

function shortVaultAddress(address) {
  if (!address || address.length <= 18) {
    return address;
  }

  return `${address.slice(0, 10)}…${address.slice(-6)}`;
}

function IdentityHero({ icon, title, body, showTitle = true }) {
  return (
    <header className="identity-center-hero">
      <div className="identity-center-hero__icon" aria-hidden="true">
        {icon}
      </div>
      <div className="identity-center-hero__copy">
        <div className={`identity-center-hero__heading${showTitle && title.main ? '' : ' identity-center-hero__heading--compact'}`}>
          <p className="identity-center-hero__kicker">{title.kicker}</p>
          {showTitle && title.main ? <h1 className="identity-center-surface__title">{title.main}</h1> : null}
        </div>
        {body ? <p className="identity-center-surface__body">{body}</p> : null}
      </div>
    </header>
  );
}

function IdentityOrDivider() {
  const { t } = useLocale();

  return (
    <div className="identity-center-divider" role="separator">
      <span>{t('vault_authorize_or')}</span>
    </div>
  );
}

export function WalletAuthorizePanel({
  authorizeParams: authorizeParamsProp = null,
} = {}) {
  const searchParams = useSearchParams();
  const { t } = useLocale();
  const authorizeParams = useMemo(
    () => authorizeParamsProp || buildAuthorizeParams(searchParams),
    [authorizeParamsProp, searchParams],
  );
  const paramsKey = useMemo(() => JSON.stringify(authorizeParams), [authorizeParams]);
  const [hintAddress, setHintAddress] = useState('');
  const [forceRegister, setForceRegister] = useState(false);
  const [registerStep, setRegisterStep] = useState('username');
  const [registerUsername, setRegisterUsername] = useState('');
  const [usernameValidity, setUsernameValidity] = useState({ status: 'idle', normalized: null });
  const [showHandoff, setShowHandoff] = useState(false);
  const [handoff, setHandoff] = useState(null);
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');
  const [busy, setBusy] = useState(false);
  const [webauthnReady, setWebauthnReady] = useState(false);
  const [hasPasskeyDriver, setHasPasskeyDriver] = useState(false);
  const [handoffDismissed, setHandoffDismissed] = useState(false);
  const [canUseCrossDeviceHandoff, setCanUseCrossDeviceHandoff] = useState(false);
  const [isMobileSurface, setIsMobileSurface] = useState(false);

  const isRegister = forceRegister || authorizeParams.mode === 'register';
  const usernameReady = usernameValidity.status === 'available' && Boolean(usernameValidity.normalized);
  const registerParams = useMemo(
    () => buildSl1eAuthorizePayload({
      mode: 'register',
      ...(usernameReady ? { username: usernameValidity.normalized } : {}),
    }),
    [usernameReady, usernameValidity.normalized, paramsKey],
  );
  const loginParams = useMemo(
    () => buildSl1eAuthorizePayload({ mode: 'login' }),
    [paramsKey],
  );
  const viewKey = showHandoff && handoff
    ? 'handoff'
    : isRegister && registerStep === 'username'
      ? 'register-username'
      : isRegister
        ? 'register'
        : 'connect';
  const screenTitle = t('header_connect_title');
  const panelAriaLabel = viewKey === 'connect' ? t('vault_authorize_intro') : screenTitle;
  const primaryCta = hintAddress ? t('header_connect_safe') : t('vault_authorize_open_cta');
  const mobilePasskeyOnly = isMobileSurface && hasPasskeyDriver;
  const registerCtaLabel = t(mobilePasskeyOnly ? 'vault_authorize_create_cta_mobile' : 'vault_authorize_create_cta');
  const registerDescLabel = t(mobilePasskeyOnly ? 'vault_authorize_register_desc_mobile' : 'vault_authorize_register_desc');
  const createSafeLinkLabel = t(mobilePasskeyOnly ? 'vault_authorize_create_cta_mobile' : 'vault_authorize_create');

  useEffect(() => {
    setHintAddress(readVaultHint());
    setForceRegister(false);
    setRegisterStep('username');
    setRegisterUsername('');
    setUsernameValidity({ status: 'idle', normalized: null });
    setShowHandoff(false);
    setHandoff(null);
    setError('');
    setStatus('');
    setBusy(false);
    setHandoffDismissed(false);
    setHasPasskeyDriver(Boolean(window.PublicKeyCredential));
    setIsMobileSurface(isPrimaryMobileDevice());
    setCanUseCrossDeviceHandoff(supportsCrossDeviceHandoff({
      handoffId: authorizeParams.handoffId,
    }));

    loadWebAuthnBrowser()
      .then(() => setWebauthnReady(true))
      .catch((exception) => setError(vaultErrorLabel(exception)));
  }, [paramsKey, authorizeParams.handoffId]);

  useEffect(() => {
    const resetBusyState = () => setBusy(false);

    window.addEventListener('pageshow', resetBusyState);
    return () => window.removeEventListener('pageshow', resetBusyState);
  }, []);

  useEffect(() => {
    if (!webauthnReady || hasPasskeyDriver || showHandoff || handoffDismissed || !canUseCrossDeviceHandoff || isRegister) {
      return undefined;
    }

    let cancelled = false;

    async function autoHandoff() {
      setBusy(true);
      setError('');

      try {
        const payload = await postSl1eJson('/api/sl1e/authorize/handoff', { ...loginParams });
        if (cancelled) {
          return;
        }

        setHandoff(await ensureHandoffQrDataUrl(payload));
        setShowHandoff(true);
        setStatus(t('vault_authorize_handoff_waiting'));
      } catch (exception) {
        if (!cancelled) {
          setError(vaultErrorLabel(exception));
        }
      } finally {
        if (!cancelled) {
          setBusy(false);
        }
      }
    }

    autoHandoff();

    return () => {
      cancelled = true;
    };
  }, [canUseCrossDeviceHandoff, handoffDismissed, hasPasskeyDriver, isRegister, loginParams, showHandoff, t, webauthnReady]);

  useEffect(() => {
    if (showHandoff && handoff && !error) {
      setStatus(t('vault_authorize_handoff_waiting'));
    }
  }, [error, handoff, showHandoff, t]);

  useEffect(() => {
    if (!showHandoff || !handoff?.handoffId) {
      return undefined;
    }

    let cancelled = false;

    async function poll() {
      try {
        const response = await fetch(`/api/sl1e/authorize/handoff/${encodeURIComponent(handoff.handoffId)}`, {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        const payload = await response.json();

        if (cancelled) {
          return;
        }

        if (payload.status === 'completed' && payload.redirectUrl) {
          if (payload.entityAddress) {
            rememberVaultHint(payload.entityAddress);
          }
          setStatus(t('vault_authorize_returning'));
          followRedirect(payload.redirectUrl);
          return;
        }

        if (payload.status === 'expired') {
          setError(t('vault_authorize_handoff_expired'));
          return;
        }
      } catch {
        // Polling is best-effort; the user can refresh to restart handoff.
      }

      if (!cancelled) {
        window.setTimeout(poll, 1500);
      }
    }

    poll();

    return () => {
      cancelled = true;
    };
  }, [handoff, showHandoff, t]);

  async function triggerAuthentication({ directHint = false } = {}) {
    setBusy(true);
    setError('');
    setStatus(t('vault_authorize_searching'));

    try {
      if (!hasPasskeyDriver || !webauthnReady) {
        throw new Error('Vault sign-in is not available in this browser.');
      }

      const webauthn = await loadWebAuthnBrowser();
      const body = { ...loginParams };

      if (directHint && hintAddress) {
        body.entityAddress = hintAddress;
      }

      let prepared;
      try {
        prepared = await postSl1eJson('/api/sl1e/authorize/options', body);
      } catch (exception) {
        if (exception.status === 404 && directHint && hintAddress) {
          forgetVaultHint();
          setHintAddress('');
          setStatus('');
          setBusy(false);
          return triggerAuthentication({ directHint: false });
        }

        throw exception;
      }

      setStatus(t('vault_authorize_open_passkey'));

      let credentialResponse;
      try {
        credentialResponse = await webauthn.startAuthentication(prepared.options);
      } catch (exception) {
        if (exception?.name === 'NotFoundError') {
          if (directHint && hintAddress) {
            forgetVaultHint();
            setHintAddress('');
            setStatus(t('vault_authorize_passkey_not_found'));
            setBusy(false);
            return;
          }

          setStatus(t('vault_authorize_passkey_not_found'));
          setBusy(false);
          startIdentityCreation();
          return;
        }

        throw exception;
      }

      setStatus(t('vault_authorize_returning'));
      const verified = await postSl1eJson('/api/sl1e/authorize/verify', {
        ...loginParams,
        flowId: prepared.flowId,
        authenticationResponse: credentialResponse,
      });

      if (verified.entityAddress) {
        rememberVaultHint(verified.entityAddress);
      }

      if (verified.handoffCompleted) {
        setStatus(t('vault_authorize_handoff_complete'));
        setBusy(false);
        return;
      }

      followRedirect(verified.redirectUrl);
    } catch (exception) {
      const message = String(exception?.message || '').toLowerCase();
      if (message.includes('credential not found in identity stream projection')) {
        forgetVaultHint();
        setHintAddress('');
        setError(vaultErrorLabel(exception));
        setStatus('');
        setBusy(false);
        return;
      }

      setError(vaultErrorLabel(exception));
      setStatus('');
      setBusy(false);
    }
  }

  async function triggerRegistration() {
    if (!usernameReady) {
      setError(t('vault_authorize_nickname_invalid'));
      return;
    }

    if (!hasPasskeyDriver || !webauthnReady) {
      if (canUseCrossDeviceHandoff) {
        await openHandoff({ register: true });
      }
      return;
    }

    setBusy(true);
    setError('');
    setStatus(t('vault_authorize_creating'));

    try {
      const webauthn = await loadWebAuthnBrowser();
      const prepared = await postSl1eJson('/api/sl1e/authorize/register/options', { ...registerParams });

      setStatus(t('vault_authorize_open_passkey'));

      let credentialResponse;
      try {
        credentialResponse = await webauthn.startRegistration(prepared.options);
      } catch (exception) {
        if (exception?.name === 'NotAllowedError') {
          setError(t('vault_authorize_registration_cancelled'));
          setStatus('');
          setBusy(false);
          return;
        }

        throw exception;
      }

      setStatus(t('vault_authorize_returning'));
      const verified = await postSl1eJson('/api/sl1e/authorize/register/verify', {
        ...registerParams,
        flowId: prepared.flowId,
        attestationResponse: credentialResponse,
      });

      if (verified.entityAddress) {
        rememberVaultHint(verified.entityAddress);
      }

      if (verified.handoffCompleted) {
        setStatus(t('vault_authorize_handoff_complete'));
        setBusy(false);
        return;
      }

      followRedirect(verified.redirectUrl);
    } catch (exception) {
      setError(vaultErrorLabel(exception, 'Could not create account.'));
      setStatus('');
      setBusy(false);
    }
  }

  async function openHandoff({ register = false } = {}) {
    if (register && !usernameReady) {
      setError(t('vault_authorize_nickname_invalid'));
      return;
    }

    setHandoffDismissed(false);
    setBusy(true);
    setError('');
    setStatus('');

    try {
      const payload = await postSl1eJson('/api/sl1e/authorize/handoff', register ? { ...registerParams } : { ...loginParams });
      setHandoff(await ensureHandoffQrDataUrl(payload));
      setShowHandoff(true);
      setStatus(t('vault_authorize_handoff_waiting'));
    } catch (exception) {
      setError(vaultErrorLabel(exception));
    } finally {
      setBusy(false);
    }
  }

  function startIdentityCreation() {
    forgetVaultHint();
    setHintAddress('');
    setError('');
    setStatus('');
    setShowHandoff(false);
    setRegisterStep('username');
    setRegisterUsername('');
    setUsernameValidity({ status: 'idle', normalized: null });
    setForceRegister(true);
  }

  function launchRegistration() {
    startIdentityCreation();
  }

  function forgetSavedIdentityHint() {
    forgetVaultHint();
    setHintAddress('');
    setError('');
    setStatus('');
    setShowHandoff(false);
    setHandoff(null);
    setHandoffDismissed(false);
  }

  async function signInWithoutSavedHint() {
    forgetSavedIdentityHint();
    await triggerAuthentication({ directHint: false });
  }

  async function continueRegistrationWithUsername() {
    if (!usernameReady) {
      setError(t('vault_authorize_nickname_invalid'));
      return;
    }

    setError('');
    setRegisterStep('passkey');
    await triggerRegistration();
  }

  function cancelRegistration() {
    setForceRegister(false);
    setRegisterStep('username');
    setRegisterUsername('');
    setUsernameValidity({ status: 'idle', normalized: null });
    setError('');
    setStatus('');
  }

  function connectOnThisDevice() {
    setShowHandoff(false);
    setForceRegister(false);
    setRegisterStep('username');
    setHandoffDismissed(true);
    setStatus('');
  }

  return (
    <section
      className={`identity-center-surface identity-center-surface--native identity-center-surface--animated ${busy ? 'is-busy' : ''}`}
      aria-label={panelAriaLabel}
      aria-busy={busy}
    >
      <IdentityStateStage stageKey={viewKey} busy={busy}>
        {viewKey === 'handoff' ? (
          <>
            <IdentityHero
              icon={<VaultPhoneIcon />}
              title={{
                kicker: t('vault_authorize_badge'),
                main: isRegister ? t('vault_authorize_register_on_phone') : t('vault_authorize_handoff_title'),
              }}
              body={isRegister ? t('vault_authorize_register_phone_desc') : t('vault_authorize_handoff_body')}
            />

            {handoff?.qrDataUrl ? (
              <div className="identity-center-qr-frame">
                <img className="identity-center-handoff-qr" src={handoff.qrDataUrl} alt={t('vault_authorize_handoff_title')} />
              </div>
            ) : null}

            <div className="identity-center-foot identity-center-foot--pill">
              <button type="button" className="meanly-pill-button" disabled={busy} onClick={connectOnThisDevice}>
                <span className="meanly-pill-button__mark" aria-hidden="true" />
                {t('vault_authorize_handoff_back')}
              </button>
            </div>
          </>
        ) : null}

        {viewKey !== 'handoff' ? (
          <>
            <IdentityHero
              icon={<VaultShieldIcon />}
              showTitle={viewKey !== 'connect' && viewKey !== 'register-username'}
              title={{
                kicker: t('vault_authorize_badge'),
                main: viewKey === 'register-username'
                  ? t('vault_authorize_nickname_title')
                  : viewKey === 'register'
                    ? t('vault_authorize_create_title')
                    : screenTitle,
              }}
              body={
                viewKey === 'register-username'
                  ? t('vault_authorize_nickname_desc')
                  : viewKey === 'register'
                    ? (hasPasskeyDriver
                      ? registerDescLabel
                      : (canUseCrossDeviceHandoff
                        ? t('vault_authorize_register_phone_desc')
                        : t('vault_authorize_no_passkey')))
                    : t('vault_authorize_intro')
              }
            />

            {viewKey === 'register-username' ? (
              <VaultUsernameField
                value={registerUsername}
                onChange={setRegisterUsername}
                onValidityChange={setUsernameValidity}
                disabled={busy}
              />
            ) : null}

            <div className="identity-center-actions">
              {viewKey === 'connect' && hasPasskeyDriver ? (
                <>
                  <button
                    type="button"
                    className="identity-center-primary"
                    disabled={busy || !webauthnReady}
                    onClick={() => triggerAuthentication({ directHint: Boolean(hintAddress) })}
                  >
                    <span className="identity-center-primary__icon" aria-hidden="true">
                      <VaultKeyIcon />
                    </span>
                    <span className="identity-center-primary__copy">
                      {hintAddress || !mobilePasskeyOnly ? (
                        <span className="identity-center-primary__eyebrow">
                          {hintAddress ? t('vault_authorize_detected') : t('vault_authorize_passkey_eyebrow')}
                        </span>
                      ) : null}
                      <strong className="identity-center-primary__title">{primaryCta}</strong>
                      {hintAddress ? (
                        <span className="identity-center-primary__hint">{shortVaultAddress(hintAddress)}</span>
                      ) : (
                        <span className="identity-center-primary__hint">{t('vault_authorize_passkey_hint')}</span>
                      )}
                    </span>
                  </button>

                  {canUseCrossDeviceHandoff ? (
                    <>
                      <IdentityOrDivider />

                      <button
                        type="button"
                        className="identity-center-secondary"
                        disabled={busy}
                        onClick={() => openHandoff()}
                      >
                        <span className="identity-center-secondary__icon" aria-hidden="true">
                          <VaultPhoneIcon />
                        </span>
                        <span className="identity-center-secondary__copy">
                          <span className="identity-center-secondary__eyebrow">{t('vault_authorize_connect_phone_eyebrow')}</span>
                          <strong className="identity-center-secondary__title">{t('vault_authorize_connect_on_phone')}</strong>
                          <span className="identity-center-secondary__hint">{t('vault_authorize_connect_phone_hint')}</span>
                        </span>
                      </button>
                    </>
                  ) : null}
                </>
              ) : null}

              {viewKey === 'connect' && !hasPasskeyDriver && !isMobileSurface ? (
                canUseCrossDeviceHandoff ? (
                  <button type="button" className="identity-center-primary" disabled={busy} onClick={() => openHandoff()}>
                    <span className="identity-center-primary__icon" aria-hidden="true">
                      <VaultPhoneIcon />
                    </span>
                    <span className="identity-center-primary__copy">
                      <span className="identity-center-primary__eyebrow">{t('vault_authorize_connect_phone_eyebrow')}</span>
                      <strong className="identity-center-primary__title">{t('vault_authorize_handoff_title')}</strong>
                      <span className="identity-center-primary__hint">{t('vault_authorize_no_passkey')}</span>
                    </span>
                  </button>
                ) : (
                  <div className="identity-center-secondary identity-center-secondary--static">
                    <span className="identity-center-secondary__copy">
                      <strong className="identity-center-secondary__title">{t('vault_authorize_open_cta')}</strong>
                      <span className="identity-center-secondary__hint">{t('vault_authorize_no_passkey')}</span>
                    </span>
                  </div>
                )
              ) : null}

              {viewKey === 'connect' && !hasPasskeyDriver && isMobileSurface ? (
                <div className="identity-center-secondary identity-center-secondary--static">
                  <span className="identity-center-secondary__copy">
                    <strong className="identity-center-secondary__title">{t('vault_authorize_open_cta')}</strong>
                    <span className="identity-center-secondary__hint">{t('vault_authorize_passkey_hint')}</span>
                  </span>
                </div>
              ) : null}

              {viewKey === 'register-username' ? (
                <button
                  type="button"
                  className="identity-center-primary"
                  disabled={busy || !usernameReady}
                  onClick={continueRegistrationWithUsername}
                >
                  <span className="identity-center-primary__icon" aria-hidden="true">
                    <VaultShieldIcon />
                  </span>
                  <span className="identity-center-primary__copy">
                    <strong className="identity-center-primary__title">
                      {t('vault_authorize_nickname_continue')}
                    </strong>
                    {usernameReady ? (
                      <span className="identity-center-primary__hint">@{usernameValidity.normalized}</span>
                    ) : null}
                  </span>
                </button>
              ) : null}

              {viewKey === 'register' ? (
                <>
                  <button
                    type="button"
                    className="identity-center-primary"
                    disabled={busy || !usernameReady || (hasPasskeyDriver && !webauthnReady) || (!hasPasskeyDriver && !canUseCrossDeviceHandoff)}
                    onClick={triggerRegistration}
                  >
                    <span className="identity-center-primary__icon" aria-hidden="true">
                      {hasPasskeyDriver ? <VaultShieldIcon /> : <VaultPhoneIcon />}
                    </span>
                    <span className="identity-center-primary__copy">
                      <strong className="identity-center-primary__title">
                        {hasPasskeyDriver ? registerCtaLabel : t('vault_authorize_handoff_title')}
                      </strong>
                      <span className="identity-center-primary__hint">
                        {usernameReady ? `@${usernameValidity.normalized} · ` : ''}
                        {hasPasskeyDriver
                          ? registerDescLabel
                          : (canUseCrossDeviceHandoff
                            ? t('vault_authorize_no_passkey')
                            : t('vault_authorize_no_passkey'))}
                      </span>
                    </span>
                  </button>

                  {canUseCrossDeviceHandoff ? (
                    <>
                      <IdentityOrDivider />
                      <button type="button" className="identity-center-secondary" disabled={busy || !usernameReady} onClick={() => openHandoff({ register: true })}>
                        <span className="identity-center-secondary__icon" aria-hidden="true">
                          <VaultPhoneIcon />
                        </span>
                        <span className="identity-center-secondary__copy">
                          <strong className="identity-center-secondary__title">{t('vault_authorize_register_on_phone')}</strong>
                        </span>
                      </button>
                    </>
                  ) : null}
                </>
              ) : null}
            </div>

            {viewKey === 'connect' && hasPasskeyDriver ? (
              <div className="identity-center-foot identity-center-foot--pill">
                {hintAddress ? (
                  <button
                    type="button"
                    className="meanly-pill-button"
                    disabled={busy}
                    onClick={() => signInWithoutSavedHint()}
                  >
                    <span className="meanly-pill-button__mark" aria-hidden="true" />
                    {t('vault_authorize_not_this_safe')}
                  </button>
                ) : null}
                <button type="button" className="meanly-pill-button" disabled={busy} onClick={launchRegistration}>
                  <span className="meanly-pill-button__mark" aria-hidden="true" />
                  {createSafeLinkLabel}
                </button>
              </div>
            ) : null}

            {viewKey === 'register-username' ? (
              <div className="identity-center-foot identity-center-foot--pill">
                <button
                  type="button"
                  className="meanly-pill-button"
                  disabled={busy}
                  onClick={cancelRegistration}
                >
                  <span className="meanly-pill-button__mark" aria-hidden="true" />
                  {t('vault_authorize_back_to_connect')}
                </button>
              </div>
            ) : null}

            {viewKey === 'register' ? (
              <div className="identity-center-foot identity-center-foot--pill">
                <button
                  type="button"
                  className="meanly-pill-button"
                  disabled={busy}
                  onClick={() => {
                    setRegisterStep('username');
                    setError('');
                    setStatus('');
                  }}
                >
                  <span className="meanly-pill-button__mark" aria-hidden="true" />
                  {t('vault_authorize_nickname_back')}
                </button>
              </div>
            ) : null}
          </>
        ) : null}
      </IdentityStateStage>

      <IdentityStatusSlot error={error} status={status} />
    </section>
  );
}
