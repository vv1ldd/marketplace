'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { MeanlyConnectPanel } from './MeanlyConnectPanel';

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
      <section className="meanly-connect-panel">
        <div>
          <p className="eyebrow">Meanly Connect</p>
          <h2>Checking session...</h2>
          <p>Meanly is checking whether this browser already has an active identity.</p>
        </div>
      </section>
    );
  }

  if (status?.authenticated) {
    const destination = safePanelReturnTo(returnTo);
    const label = status.identity?.username
      ? `@${status.identity.username}`
      : status.identity?.display_alias || status.identity?.alias || status.identity?.entity_l1_address || 'Meanly identity';

    return (
      <section className="meanly-connect-panel meanly-session-panel">
        <div>
          <p className="eyebrow">Already connected</p>
          <h2>You are signed in.</h2>
          <p>{label} is already active in this browser.</p>
        </div>
        <div className="product-card__actions">
          <Link href={destination}>{destination.startsWith('/merchant') ? 'Open Merchant Center' : 'Continue'}</Link>
          <Link href="/">Browse marketplace</Link>
          {destination.startsWith('/merchant') ? null : <Link href="/merchant">Merchant Center</Link>}
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
