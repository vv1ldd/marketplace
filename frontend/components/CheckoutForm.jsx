'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { MeanlyConnectPanel } from './MeanlyConnectPanel';
import {
  createCheckout,
  fetchCheckoutIntent,
  simpleL1ConnectUrl,
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

export function CheckoutForm({ productId }) {
  const [token, setToken] = useState('');
  const [email, setEmail] = useState('');
  const [intent, setIntent] = useState(null);
  const [order, setOrder] = useState(null);
  const [error, setError] = useState('');
  const connectUrl = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentType: 'meanly.checkout.continue',
    intentTitle: 'Continue checkout with Meanly',
    intentDescription: 'Sign in to create the order and save it to your vault.',
  });

  useEffect(() => {
    setToken(storedToken());
  }, []);

  async function previewIntent() {
    setError('');
    setIntent(null);
    setOrder(null);

    try {
      const payload = await fetchCheckoutIntent({
        product_id: Number(productId),
        quantity: 1,
      });
      setIntent(payload.intent);
    } catch (exception) {
      setError(exception.message);
    }
  }

  async function submit(event) {
    event.preventDefault();
    setError('');
    setOrder(null);

    try {
      const checkoutPayload = {
        product_id: Number(productId),
        quantity: 1,
        email,
      };
      const created = await createCheckout(checkoutPayload, token);
      setOrder(created);
    } catch (exception) {
      setError(exception.message);
    }
  }

  return (
    <form className="panel" onSubmit={submit}>
      <p className="product-card__muted">
        Review the order first. Sign in only when you are ready to create it.
      </p>
      {!token ? (
        <MeanlyConnectPanel
          href={connectUrl}
          title="Sign in to checkout."
          body="Meanly keeps your order, receipt, and delivery details connected to your vault."
          secondaryHref="/"
          secondaryLabel="Keep browsing"
        />
      ) : null}
      <label>
        Delivery email
        <input value={email} onChange={(event) => setEmail(event.target.value)} placeholder="buyer@example.test" />
      </label>
      <div className="product-card__actions">
        <button type="button" onClick={previewIntent}>Preview order</button>
        <button disabled={!token} type="submit">Place order with Meanly</button>
      </div>
      <details className="advanced-panel">
        <summary>Advanced token tools</summary>
        <label>
          Storefront Token
          <input value={token} onChange={(event) => setToken(event.target.value)} placeholder="sft_..." />
        </label>
      </details>
      {error ? <p className="product-card__reason">{error}</p> : null}
      {intent ? (
        <section className="checkout-note">
          <strong>Checkout preview is ready.</strong>
          <p>Sign in with Meanly to place the order and save it to your Vault.</p>
        </section>
      ) : null}
      {order ? (
        <div>
          <p>Order: {order.order_id}</p>
          <Link href={`/orders/${order.order_uuid}/safe`}>Open order-safe status</Link>
        </div>
      ) : null}
    </form>
  );
}
