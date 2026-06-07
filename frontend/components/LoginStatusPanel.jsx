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

export function LoginStatusPanel({ connectUrl }) {
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
    const label = status.identity?.display_alias || status.identity?.alias || status.identity?.entity_l1_address || 'Meanly identity';

    return (
      <section className="meanly-connect-panel meanly-session-panel">
        <div>
          <p className="eyebrow">Already connected</p>
          <h2>You are signed in.</h2>
          <p>{label} is already active in this browser.</p>
        </div>
        <div className="product-card__actions">
          <Link href="/vault">Open Vault</Link>
          <Link href="/">Browse marketplace</Link>
          <Link href="/business">Merchant Center</Link>
        </div>
      </section>
    );
  }

  return (
    <MeanlyConnectPanel
      href={connectUrl}
      title="Open Meanly to continue."
      body="Approve sign-in in Meanly. If nothing opens, continue in the browser."
      secondaryHref="/vault"
      secondaryLabel="Open Vault"
    />
  );
}
