'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useRef, useState } from 'react';
import { BusinessOnboardingStatus } from './BusinessOnboardingStatus';
import { MeanlyConnectPanel } from './MeanlyConnectPanel';
import {
  claimSimpleL1Handoff,
  fetchPartnerRegistrationState,
  frontendUrl,
  simpleL1ConnectUrl,
  storefrontTokenStorageKey,
} from '../lib/storefront-api';

const PARTNER_SCOPES = [
  'storefront:read',
  'storefront:checkout',
  'storefront:vault',
  'storefront:partner-registration',
];

function storedToken() {
  try {
    return window.localStorage.getItem(storefrontTokenStorageKey) || '';
  } catch {
    return '';
  }
}

function persistToken(token) {
  try {
    window.localStorage.setItem(storefrontTokenStorageKey, token);
  } catch {
    // Browser storage is only a handoff cache.
  }
}

function clearStoredToken() {
  try {
    window.localStorage.removeItem(storefrontTokenStorageKey);
  } catch {
    // Ignore storage failures; the next Connect attempt can issue a new token.
  }
}

function blockingReasonLabel(reason) {
  return {
    simple_l1_identity_required: 'Continue with Meanly before seller onboarding can proceed.',
  }[reason] || reason;
}

function nextStepLabel(action) {
  return {
    CONNECT_SIMPLE_L1: 'Connect identity',
    SUBMIT_LEGAL_ENTITY: 'Add company details',
    REQUEST_SELLER_AUTHORITY: 'Request seller access',
    WAIT_FOR_REVIEW: 'Waiting for review',
  }[action] || 'Next step ready';
}

export function PartnerRegistrationPanel({
  claimHandoff = false,
  initialProjection,
  initialOnboardingChecked = false,
  initialOnboardingPayload = null,
}) {
  const router = useRouter();
  const [projection, setProjection] = useState(initialProjection || {});
  const [sessionApplicationChecked, setSessionApplicationChecked] = useState(initialOnboardingChecked);
  const [sessionOnboardingPayload, setSessionOnboardingPayload] = useState(initialOnboardingPayload);
  const [hasSessionApplication, setHasSessionApplication] = useState(Boolean(initialOnboardingPayload?.legal_entity));
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const handoffClaimed = useRef(false);
  const state = projection.state || {};
  const needsIdentity = state.next_action === 'CONNECT_SIMPLE_L1';
  const hasExistingApplication = Boolean(state.existing_application)
    || hasSessionApplication
    || state.next_action === 'VIEW_ONBOARDING_STATUS';
  const connectUrl = simpleL1ConnectUrl({
    returnTo: frontendUrl('/merchant/register', { sl1_handoff: 1 }),
    mode: 'connect',
    intentType: 'meanly.partner.onboarding',
    intentTitle: 'Start partner onboarding',
    intentCta: 'Continue with Meanly',
    intentDescription: 'Use your Meanly identity to start partner onboarding.',
  });

  useEffect(() => {
    if (initialOnboardingChecked) {
      return;
    }

    const token = storedToken();

    if (token) {
      loadStateWith(token);
    }

    fetch('/backend/business/register/onboarding', {
      credentials: 'include',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    })
      .then((response) => response.ok ? response.json() : null)
      .then((payload) => {
        if (payload?.redirect === '/partner' || payload?.redirect === '/merchant') {
          router.push('/merchant');
          return;
        }

        if (payload?.legal_entity) {
          setSessionOnboardingPayload(payload);
          setHasSessionApplication(true);
        }
      })
      .catch(() => null)
      .finally(() => setSessionApplicationChecked(true));
  }, [initialOnboardingChecked]);

  useEffect(() => {
    if (!claimHandoff || handoffClaimed.current) {
      return;
    }

    handoffClaimed.current = true;
    claimBackendHandoff();
  }, [claimHandoff]);

  async function claimBackendHandoff() {
    setError('');
    setStatus('Opening seller onboarding...');

    try {
      const issued = await claimSimpleL1Handoff(PARTNER_SCOPES);
      persistToken(issued.access_token);
      await loadStateWith(issued.access_token);
      setStatus('Meanly identity verified. Continue seller onboarding.');
      window.history.replaceState(null, '', '/merchant/register');
    } catch (exception) {
      if (exception.status === 410) {
        setStatus('Approval expired. Continue with Meanly again to start seller onboarding.');
      } else if (exception.status === 401) {
        setStatus('Approval was not received yet. Continue with Meanly and approve sign-in in the app.');
      } else {
        setStatus('');
      }

      setError(
        exception.message === 'Load failed'
          ? 'Seller handoff could not reach Meanly. Open the marketplace on meanly.test and continue with Meanly again.'
          : exception.message,
      );
    }
  }

  async function loadStateWith(token) {
    try {
      const nextProjection = await fetchPartnerRegistrationState(token);
      setProjection(nextProjection);
    } catch (exception) {
      if ([401, 403].includes(exception.status)) {
        clearStoredToken();
      }

      setError(exception.message || 'Seller onboarding state is unavailable right now.');
    }
  }

  if (!sessionApplicationChecked) {
    return (
      <section className="panel">
        <h2>Checking seller onboarding...</h2>
        <p className="product-card__muted">Meanly is checking whether this identity already has a merchant application.</p>
      </section>
    );
  }

  if (hasExistingApplication) {
    return <BusinessOnboardingStatus initialPayload={sessionOnboardingPayload} />;
  }

  return (
    <section className="panel">
      <h2>{needsIdentity ? 'Continue partner onboarding.' : 'Partner onboarding is ready.'}</h2>
      <p className="product-card__muted">
        {needsIdentity
          ? 'Meanly verifies the identity starting this seller journey before Merchant Center opens.'
          : 'Your identity is connected. The next step is adding company details and requesting seller authority.'}
      </p>
      {state.blocking_reason ? <p className="product-card__reason">{blockingReasonLabel(state.blocking_reason)}</p> : null}
      {status ? <p className="checkout-note">{status}</p> : null}
      {error ? <p className="product-card__reason">{error}</p> : null}
      {needsIdentity ? (
        <MeanlyConnectPanel
          href={connectUrl}
          title="Connect to sell with Meanly."
          body="Continue in browser now. The Meanly One app path is coming soon."
          secondaryHref="/"
          secondaryLabel="Browse marketplace"
        />
      ) : (
        <div className="product-card__actions">
          <Link href="/legal-entities/register">Add company by INN</Link>
          <Link href="/merchant">Open merchant workspace</Link>
          <span>{nextStepLabel(state.next_action)}</span>
        </div>
      )}
    </section>
  );
}
