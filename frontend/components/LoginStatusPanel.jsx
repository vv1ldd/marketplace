'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { MeanlyConnectPanel } from './MeanlyConnectPanel';
import { MeanlyLoadingMark } from './MeanlyLoadingMark';

async function fetchSimpleL1Status() {
  const response = await fetch('/simple-l1/status', {
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    return null;
  }

  return response.json().catch(() => null);
}

function safePanelReturnTo(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : '/vault';
}

export function LoginStatusPanel({ connectUrl, returnTo = '/vault' }) {
  const [status, setStatus] = useState(null);
  const [checked, setChecked] = useState(false);

  useEffect(() => {
    let isMounted = true;

    fetchSimpleL1Status()
      .then((payload) => {
        if (isMounted) {
          setStatus(payload);
        }
      })
      .finally(() => {
        if (isMounted) {
          setChecked(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  if (!checked) {
    return (
      <section className="meanly-connect-panel meanly-connect-panel--loading">
        <MeanlyLoadingMark label="Checking session..." size="md" />
      </section>
    );
  }

  if (status?.authenticated) {
    const destination = safePanelReturnTo(returnTo);
    const label = status.identity?.username
      ? `@${status.identity.username}`
      : status.identity?.display_alias || status.identity?.alias || status.identity?.entity_l1_address || 'Meanly identity';

    return (
      <section className="connect-card meanly-connect-panel meanly-session-panel">
        <span className="connect-card__mark" aria-hidden="true" />
        <p className="eyebrow">Already connected</p>
        <h2>You are signed in.</h2>
        <p>{label} is already active in this browser.</p>
        <div className="connect-card__actions">
          <Link className="connect-cta connect-cta--primary" href={destination}>
            {destination.startsWith('/merchant') ? 'Open Merchant Center' : 'Continue'}
          </Link>
          <Link className="connect-cta connect-cta--ghost" href="/">Browse marketplace</Link>
          {destination.startsWith('/merchant') ? null : (
            <Link className="connect-cta connect-cta--ghost" href="/merchant">Merchant Center</Link>
          )}
        </div>
      </section>
    );
  }

  return (
    <MeanlyConnectPanel
      href={connectUrl}
      title="Open Meanly to continue."
      body="Approve sign-in in Meanly One. If the app cannot return here, continue in browser."
      secondaryHref="/vault"
      secondaryLabel="Open Vault"
    />
  );
}
