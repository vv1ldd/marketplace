'use client';

import Link from 'next/link';
import { useCallback, useState } from 'react';
import { refreshStorefrontVaultToken, revealVaultEntitlement } from '../../lib/storefront-api';
import { readStoredVaultToken } from '../../lib/vault-authority';
import { useLocale } from '../LocaleProvider';

function scratchLabel(t) {
  return t('vault_dashboard_scratch_here');
}

export function VaultEntitlementCard({ item, onRevealed }) {
  const { t } = useLocale();
  const [phase, setPhase] = useState(item?.is_revealed ? 'opened' : 'hidden');
  const [secret, setSecret] = useState('');
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  const revealSecret = useCallback(async () => {
    if (!item?.id || phase === 'loading' || (phase === 'revealed' && secret)) {
      return;
    }

    setError('');
    setPhase('loading');

    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setPhase(item?.is_revealed ? 'opened' : 'hidden');
        setError(t('vault_dashboard_reveal_session_expired'));
        return;
      }
    }

    try {
      const payload = await revealVaultEntitlement(item.id, token);
      setSecret(payload.secret || '');
      setPhase('revealed');
      onRevealed?.(item.id, payload.secret || '');
    } catch (exception) {
      if (exception.status === 403) {
        try {
          const freshToken = await refreshStorefrontVaultToken();
          const payload = await revealVaultEntitlement(item.id, freshToken);
          setSecret(payload.secret || '');
          setPhase('revealed');
          onRevealed?.(item.id, payload.secret || '');
          return;
        } catch (retryException) {
          setError(retryException.message || t('vault_dashboard_reveal_failed'));
        }
      } else {
        setError(exception.message || t('vault_dashboard_reveal_failed'));
      }

      setPhase(item?.is_revealed ? 'opened' : 'hidden');
    }
  }, [item?.id, item?.is_revealed, onRevealed, phase, secret, t]);

  async function copySecret() {
    if (!secret) {
      return;
    }

    try {
      await navigator.clipboard.writeText(secret);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1800);
    } catch {
      setCopied(false);
    }
  }

  const showScratch = phase === 'hidden';
  const showLoading = phase === 'loading';
  const showSecret = phase === 'revealed' && secret;
  const showOpenedPrompt = phase === 'opened' && !secret;

  return (
    <article className="vault-card vault-inventory-card vault-entitlement-card">
      <div className="vault-inventory-card__top">
        <strong>{item.brand}</strong>
        <span className="vault-inventory-card__region">{item.region}</span>
      </div>

      <div className="vault-inventory-card__denomination">
        {item.denomination} {item.currency}
      </div>

      <div className="vault-inventory-card__intent">{item.intent_key}</div>

      <div className={`vault-entitlement-card__secret-shell${showSecret ? ' is-revealed' : ''}`}>
        {showSecret ? (
          <div className="vault-entitlement-card__secret-value">
            <code>{secret}</code>
            <button className="vault-entitlement-card__copy" onClick={copySecret} type="button">
              {copied ? t('vault_dashboard_secret_copied') : t('vault_dashboard_copy_secret')}
            </button>
          </div>
        ) : null}

        {showLoading ? (
          <div className="vault-entitlement-card__scratch vault-entitlement-card__scratch--loading" aria-busy="true">
            <span className="vault-dashboard-skeleton vault-dashboard-skeleton--title" />
          </div>
        ) : null}

        {showScratch ? (
          <button
            className="vault-entitlement-card__scratch"
            onClick={revealSecret}
            type="button"
          >
            <span className="vault-entitlement-card__scratch-label">{scratchLabel(t)}</span>
          </button>
        ) : null}

        {showOpenedPrompt ? (
          <button
            className="vault-inventory-card__cta vault-inventory-card__cta--active"
            onClick={revealSecret}
            type="button"
          >
            {t('vault_dashboard_view_code')}
          </button>
        ) : null}
      </div>

      {item.activation_url ? (
        <Link className="vault-entitlement-card__activation" href={item.activation_url}>
          {t('vault_dashboard_activation_guide')}
        </Link>
      ) : null}

      {error ? <p className="vault-entitlement-card__error">{error}</p> : null}
    </article>
  );
}
