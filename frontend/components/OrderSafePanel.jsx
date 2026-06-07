'use client';

import { useEffect, useState } from 'react';
import { MeanlyConnectPanel } from './MeanlyConnectPanel';
import {
  fetchOrderSafeStatus,
  fetchOrderSafeSupport,
  openOrderSafe,
  simpleL1ConnectUrl,
  scratchOrderSafe,
  storefrontTokenStorageKey,
  vaultHandoffUrl,
} from '../lib/storefront-api';

function storedToken() {
  try {
    return window.localStorage.getItem(storefrontTokenStorageKey) || '';
  } catch {
    return '';
  }
}

function safeCodeLabel(code) {
  if (typeof code === 'string') {
    return code;
  }

  return code?.code || code?.value || code?.pin || code?.serial || 'Safe code ready';
}

export function OrderSafePanel({ orderUuid }) {
  const [token, setToken] = useState('');
  const [decision, setDecision] = useState(null);
  const [codes, setCodes] = useState([]);
  const [support, setSupport] = useState(null);
  const [scratchProof, setScratchProof] = useState('');
  const [error, setError] = useState('');
  const connectUrl = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentType: 'meanly.order_safe.open',
    intentTitle: 'Open order-safe with Meanly',
    intentDescription: 'Sign in to view order status and safe codes.',
  });

  useEffect(() => {
    setToken(storedToken());
  }, []);

  async function load(event) {
    event.preventDefault();
    setError('');
    setDecision(null);
    setCodes([]);
    setSupport(null);

    try {
      setDecision(await fetchOrderSafeStatus(orderUuid, token));
    } catch (exception) {
      setError(exception.message);
    }
  }

  async function openSafe() {
    setError('');
    setCodes([]);

    try {
      const payload = await openOrderSafe(orderUuid, token);
      setDecision(payload.decision);
      setCodes(payload.codes || []);
    } catch (exception) {
      setError(exception.message);
    }
  }

  async function scratchSafe() {
    setError('');

    try {
      const payload = await scratchOrderSafe(orderUuid, token, scratchProof);
      setDecision(payload.decision);
    } catch (exception) {
      setError(exception.message);
    }
  }

  async function loadSupport() {
    setError('');
    setSupport(null);

    try {
      setSupport(await fetchOrderSafeSupport(orderUuid, token));
    } catch (exception) {
      setError(exception.message);
    }
  }

  return (
    <section className="panel">
      <form className="stack" onSubmit={load}>
        <p className="product-card__muted">
          Sign in to view this order, reveal codes, or contact support.
        </p>
        {!token ? (
          <MeanlyConnectPanel
            href={connectUrl}
            title="Sign in to open order safe."
            body="Your order-safe page keeps delivery codes and support actions in one place."
            secondaryHref="/vault"
            secondaryLabel="Open Vault"
          />
        ) : null}
        <label>
          Scratch proof
          <input value={scratchProof} onChange={(event) => setScratchProof(event.target.value)} placeholder="user-presence-or-delivery-proof" />
        </label>
        <div className="product-card__actions">
          <button disabled={!token} type="submit">Check order status</button>
          <button disabled={!token} type="button" onClick={openSafe}>Open safe</button>
          <button disabled={!token} type="button" onClick={scratchSafe}>Scratch delivered</button>
          <button disabled={!token} type="button" onClick={loadSupport}>Support status</button>
        </div>
        <details className="advanced-panel">
          <summary>Advanced token tools</summary>
          <label>
            Storefront Token
            <input value={token} onChange={(event) => setToken(event.target.value)} placeholder="sft_..." />
          </label>
        </details>
      </form>
      {error ? <p className="product-card__reason">{error}</p> : null}
      {decision ? (
        <section className="checkout-note">
          <strong>Order-safe status: {decision.status || 'ready'}</strong>
          <p>
            {(decision.allowed_actions || []).length
              ? `${decision.allowed_actions.length} safe action(s) available.`
              : 'No safe actions are available yet.'}
          </p>
        </section>
      ) : null}
      {codes.length > 0 ? (
        <section className="panel">
          <h2>Safe codes</h2>
          <div className="seller-card-list">
            {codes.map((code, index) => (
              <article className="seller-mini-card" key={`${safeCodeLabel(code)}-${index}`}>
                <strong>{safeCodeLabel(code)}</strong>
                {code?.expires_at ? <span>Expires {code.expires_at}</span> : null}
              </article>
            ))}
          </div>
        </section>
      ) : null}
      {support ? (
        <section className="checkout-note">
          <strong>Support status is ready.</strong>
          <p>{support.ticket?.status || support.decision?.status || 'No open support action required.'}</p>
        </section>
      ) : null}
    </section>
  );
}
