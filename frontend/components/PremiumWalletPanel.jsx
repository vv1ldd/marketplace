'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { fetchPremiumWalletAssets } from '../lib/storefront-api';
import { clearVaultAuthorityState, readStoredVaultToken } from '../lib/vault-authority';
import { GlossaryHint } from './GlossaryHint';

function identityLabel(identity = {}) {
  if (identity.username) return `@${identity.username}`;
  return identity.display_alias || identity.alias || identity.entity_l1_address || 'Vault identity';
}

export function VaultWalletContent({ wallet, status = '', error = '', isVaultOpen = false, showOpenAction = true }) {
  const coins = Array.isArray(wallet?.coins) ? wallet.coins : Array.isArray(wallet?.assets) ? wallet.assets : [];
  const hasWallet = Boolean(wallet);

  return (
    <>
      <div className="premium-wallet-shell__header">
        <span>
          Vault wallet
          <GlossaryHint>A protected coin surface for SL1, MCR, and MLP.</GlossaryHint>
        </span>
        <strong>{hasWallet ? wallet.wallet?.label || 'Vault Wallet' : isVaultOpen ? 'Vault Wallet' : 'Open Vault to preview wallet.'}</strong>
        <p>
          {hasWallet
            ? wallet.wallet?.custody_note || 'SL1, MCR, and MLP stay bound to your Vault identity.'
            : isVaultOpen
              ? 'Loading Vault Wallet coins bound to this Vault.'
            : 'Wallet preview uses your Vault identity. Open Vault first, then return here.'}
        </p>
      </div>

      {hasWallet ? (
        <>
          <div className="premium-wallet-identity">
            <span>Identity</span>
            <strong>{identityLabel(wallet.identity)}</strong>
            <small>{wallet.contract?.network || 'simple-layer-1'} · {wallet.contract?.mode || 'preview'}</small>
          </div>

          <div className="premium-wallet-assets">
            {coins.map((coin) => (
              <article className="premium-wallet-asset" key={coin.key}>
                <span>{coin.symbol}</span>
                <strong>{coin.display_amount}</strong>
                <p>{coin.name}</p>
                <small>{coin.note}</small>
              </article>
            ))}
          </div>

          <div className="premium-wallet-capabilities">
            <span>Capabilities</span>
            <strong>{wallet.capabilities?.next_action || 'PREVIEW_COINS'}</strong>
            <p>Coin redemption, transfer, and conversion stay disabled until wallet authority is connected.</p>
          </div>
        </>
      ) : (
        <div className="premium-wallet-empty">
          <span>Vault wallet preview</span>
          <strong>{error || status || 'Vault is not open yet.'}</strong>
          <p>{isVaultOpen ? 'SL1, MCR, and MLP will appear here.' : 'Open Vault to bind SL1, MCR, and MLP to your trusted identity.'}</p>
          {showOpenAction ? <Link href="/vault">Open Vault</Link> : null}
        </div>
      )}
    </>
  );
}

export function PremiumWalletPanel() {
  const [wallet, setWallet] = useState(null);
  const [status, setStatus] = useState('Loading wallet preview...');
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function loadWallet() {
      const token = readStoredVaultToken();
      if (!token) {
        setStatus('');
        setError('');
        setWallet(null);
        return;
      }

      try {
        const payload = await fetchPremiumWalletAssets(token);
        if (cancelled) return;
        setWallet(payload);
        setStatus('');
        setError('');
      } catch (exception) {
        if (cancelled) return;
        if ([401, 403].includes(exception.status)) {
          clearVaultAuthorityState();
        }
        setWallet(null);
        setStatus('');
        setError(exception.message || 'Wallet preview is unavailable.');
      }
    }

    loadWallet();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <main className="page page--wallet">
      <section className="premium-wallet-shell">
        <VaultWalletContent wallet={wallet} status={status} error={error} />
      </section>
    </main>
  );
}
