'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { frontendUrl, simpleL1ConnectUrl } from '../lib/storefront-api';

function launchApp(deepLinkUrl) {
  if (!deepLinkUrl) {
    return false;
  }

  window.location.assign(deepLinkUrl);
  return true;
}

async function jsonRequest(path, { body, method = 'GET', csrfToken } = {}) {
  const response = await fetch(`/backend${path}`, {
    method,
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(payload.error || payload.message || `Request failed: ${response.status}`);
    error.payload = payload;
    throw error;
  }

  return payload;
}

function localRedirect(path) {
  if (!path) {
    return '/business/register';
  }

  try {
    const url = new URL(path);
    const localPath = `${url.pathname}${url.search}${url.hash}`;
    return localPath.startsWith('/partner/onboarding') ? '/business/register/onboarding' : localPath;
  } catch {
    return String(path).startsWith('/partner/onboarding') ? '/business/register/onboarding' : path;
  }
}

export function BusinessOfferForm() {
  const [offer, setOffer] = useState(null);
  const [csrfToken, setCsrfToken] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [onlineUrl, setOnlineUrl] = useState('');
  const [isBusy, setIsBusy] = useState(false);
  const finalizedRef = useRef(false);

  const legalName = offer?.registration?.legal_name || 'your company';
  const signUrl = useMemo(() => {
    if (!offer?.signing?.nonce || !offer?.signing?.resource) {
      return '';
    }

    return simpleL1ConnectUrl({
      returnTo: frontendUrl('/business/register/offer', { sl1e_offer_complete: 1 }),
      mode: 'login',
      intentType: 'agreement.sign',
      intentTitle: 'Sign public offer',
      intentDescription: `You are signing "${offer.agreement?.title || 'Public offer'}" on behalf of ${legalName}.`,
      intentCta: 'Confirm signature',
      intentNonce: offer.signing.nonce,
      intentResource: offer.signing.resource,
    });
  }, [legalName, offer]);

  async function loadOffer() {
    setError('');

    try {
      const query = window.location.search || '';
      const [payload, csrfPayload] = await Promise.all([
        jsonRequest(`/business/register/offer${query}`),
        jsonRequest('/csrf-token'),
      ]);
      setOffer(payload);
      setCsrfToken(csrfPayload.csrf_token || '');
    } catch (exception) {
      const redirect = exception.payload?.redirect;
      if (redirect) {
        window.location.href = localRedirect(redirect);
        return;
      }
      setError(exception.message);
    }
  }

  async function finalizeSignature() {
    if (finalizedRef.current || !csrfToken) {
      return;
    }

    finalizedRef.current = true;
    setIsBusy(true);
    setStatus('Meanly proof received. Finalizing the offer signature...');
    setError('');

    try {
      const result = await jsonRequest('/partner/register/sign', {
        method: 'POST',
        csrfToken,
        body: { simple_l1_sign: true },
      });

      if (result.success) {
        setStatus('Offer signed. Opening onboarding...');
        window.location.href = result.redirect ? localRedirect(result.redirect) : '/partner';
        return;
      }

      throw new Error(result.error || 'Could not finalize the offer signature.');
    } catch (exception) {
      finalizedRef.current = false;
      setError(exception.message);
      setStatus('');
    } finally {
      setIsBusy(false);
    }
  }

  function startSignature() {
    if (!confirmed || !signUrl) {
      return;
    }

    setIsBusy(true);
    setStatus('Opening Meanly One to confirm the signature...');
    setOnlineUrl('');

    fetch(signUrl, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Meanly handoff failed.');
        }

        return response.json();
      })
      .then((payload) => {
        const fallbackUrl = payload.redirect_url || signUrl;
        setOnlineUrl(fallbackUrl);

        if (!launchApp(payload.deep_link_url)) {
          setStatus('Meanly One app link is not available here. Continue online with the SL1 provider if needed.');
        }
      })
      .catch(() => {
        setOnlineUrl(signUrl);
        setStatus('Meanly One handoff is unavailable here. Continue online with the SL1 provider if needed.');
      })
      .finally(() => {
        setIsBusy(false);
      });
  }

  useEffect(() => {
    loadOffer();
  }, []);

  useEffect(() => {
    if (!offer || !csrfToken) {
      return;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('sl1e_offer_complete') === '1') {
      finalizeSignature();
    }
  }, [csrfToken, offer]);

  return (
    <section className="business-register-panel business-offer-panel">
      <div className="business-register-card">
        <span>Final step</span>
        <h2>{offer?.agreement?.title || 'Public offer'}</h2>
        <p>Review the offer and confirm your authority to sign it.</p>

        <div className="business-offer-document">
          <strong>{offer?.agreement?.title || 'Loading offer...'}</strong>
          {offer?.agreement?.published_at ? <small>Published: {offer.agreement.published_at}</small> : null}
          <div>{offer?.agreement?.text || 'Loading offer text...'}</div>
        </div>

        <label className="business-offer-confirm">
          <input
            checked={confirmed}
            disabled={!offer || isBusy}
            onChange={(event) => setConfirmed(event.target.checked)}
            type="checkbox"
          />
          <span>
            I confirm that I am authorized to sign documents on behalf of <strong>{legalName}</strong> and accept the offer.
          </span>
        </label>

        <button disabled={!offer || !confirmed || isBusy || !signUrl} onClick={startSignature} type="button">
          Sign offer
        </button>
      </div>

      {status ? <p className="checkout-note">{status}</p> : null}
      {onlineUrl ? (
        <p className="meanly-connect-status">
          Browser fallback:{' '}
          <a href={onlineUrl}>Continue online</a>
        </p>
      ) : null}
      {error ? <p className="product-card__reason">{error}</p> : null}
    </section>
  );
}
