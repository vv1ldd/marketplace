'use client';

import { buildPolygonUsdcReceiveQrDataUrl } from '../lib/identity-wallet-qr';
import {
  shortenAddress,
} from '../lib/identity-wallets';
import { GlossaryHint } from './GlossaryHint';
import { VaultQrIcon, VaultShieldIcon } from './IdentityVaultIcons';
import { useLocale } from './LocaleProvider';
import { useCallback, useState } from 'react';

function identityLabel(identity = {}) {
  if (identity.username) return `@${identity.username}`;
  return identity.display_alias || identity.alias || identity.entity_l1_address || 'Vault identity';
}

function SafeReceiveQr({ address, t }) {
  const [qrOpen, setQrOpen] = useState(false);
  const [qrDataUrl, setQrDataUrl] = useState('');
  const [qrLoading, setQrLoading] = useState(false);

  const toggleQr = useCallback(async () => {
    if (qrOpen) {
      setQrOpen(false);
      return;
    }

    setQrLoading(true);

    try {
      const dataUrl = qrDataUrl || await buildPolygonUsdcReceiveQrDataUrl(address);
      if (dataUrl) {
        setQrDataUrl(dataUrl);
        setQrOpen(true);
      }
    } catch {
      setQrOpen(false);
    } finally {
      setQrLoading(false);
    }
  }, [address, qrDataUrl, qrOpen]);

  if (!address) {
    return null;
  }

  return (
    <div className="vault-safe-receive">
      <span className="vault-safe-receive__label">{t('wallet_safe_receive')}</span>
      <div className="vault-safe-receive__anchor">
        <button
          aria-expanded={qrOpen}
          className={`vault-safe-receive__trigger${qrOpen ? ' is-active' : ''}`}
          disabled={qrLoading}
          onClick={toggleQr}
          type="button"
        >
          <VaultQrIcon />
          <span>{qrOpen ? t('wallet_identity_qr') : t('wallet_safe_receive_cta')}</span>
        </button>
        {qrOpen && qrDataUrl ? (
          <div className="vault-safe-receive__popover" role="dialog">
            <img alt={t('wallet_identity_qr_alt')} src={qrDataUrl} />
            <p>{t('wallet_identity_qr_hint')}</p>
            <code>{address}</code>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export function SafeWelcomeCard({
  identity,
  managedWalletsEnabled,
  isProvisioning,
  provisionError,
  onCreateSafe,
  connectNotice = null,
  connectPhase = '',
}) {
  const { t } = useLocale();

  return (
    <section className="vault-safe-card">
      <header className="vault-safe-welcome__header">
        <div className="vault-safe-welcome__icon" aria-hidden="true">
          <VaultShieldIcon />
        </div>
        <div className="vault-safe-welcome__copy">
          <span>{t('wallet_safe_welcome_kicker')}</span>
          <strong>{identityLabel(identity)}</strong>
          <small className="vault-safe-welcome__status">{t('wallet_safe_status_durable')}</small>
        </div>
      </header>

      <div className="vault-safe-provisioning">
        <h2>{t(managedWalletsEnabled ? 'wallet_safe_welcome_title' : 'wallet_safe_welcome_title_identity')}</h2>
        <p>{t(managedWalletsEnabled ? 'wallet_safe_welcome_body' : 'wallet_safe_welcome_body_identity')}</p>
        {managedWalletsEnabled ? (
          <button
            className="vault-safe-provisioning__primary"
            disabled={isProvisioning}
            onClick={onCreateSafe}
            type="button"
          >
            {isProvisioning ? t('wallet_create_safe_opening') : t('wallet_create_safe_cta')}
          </button>
        ) : (
          <p className="vault-safe-provisioning__note">{t('wallet_safe_managed_unavailable')}</p>
        )}
        {provisionError ? <p className="vault-safe-provisioning__error">{provisionError}</p> : null}
        {connectNotice?.message ? (
          <p className="vault-safe-provisioning__notice">{connectNotice.message}</p>
        ) : null}
        {connectPhase ? (
          <p className="vault-safe-provisioning__phase">{connectPhase}</p>
        ) : null}
      </div>
    </section>
  );
}

export function SafeDashboardCard({
  identity,
  polygonWallet,
  summaryCoins = [],
  onRefreshWallet,
  refreshingWallet = false,
  observationState = 'none',
}) {
  const { t } = useLocale();
  const address = polygonWallet?.address || '';
  const coins = summaryCoins.length
    ? summaryCoins
    : (polygonWallet?.preview?.coins || []);

  return (
    <section className="vault-safe-dashboard">
      <header className="vault-safe-dashboard__header">
        <div className="vault-safe-dashboard__icon" aria-hidden="true">
          <VaultShieldIcon />
        </div>
        <div className="vault-safe-dashboard__copy">
          <span>{t('wallet_safe_dashboard_kicker')}</span>
          <strong>{t('wallet_safe_dashboard_title')}</strong>
          {address ? <code>{shortenAddress(address, 10, 8)}</code> : null}
          <div className="vault-safe-dashboard__badges">
            <span className="vault-safe-dashboard__badge vault-safe-dashboard__badge--connected">
              {t('wallet_binding_connected')}
            </span>
            <span className="vault-safe-dashboard__badge vault-safe-dashboard__badge--bound">
              {t('wallet_safe_identity_bound')}
            </span>
          </div>
        </div>
      </header>

      {address ? (
        <>
          <div className="vault-safe-dashboard__identity-line">
            <span>{t('wallet_identity_name')}</span>
            <strong>{identityLabel(identity)}</strong>
          </div>
          <SafeReceiveQr address={address} t={t} />
        </>
      ) : null}

      <div className="vault-safe-dashboard__assets">
        <span className="vault-safe-dashboard__assets-label">
          {t('wallet_binding_assets')}
          <GlossaryHint>{t('wallet_bindings_hint')}</GlossaryHint>
        </span>
        {coins.length ? (
          <div className="premium-wallet-balance-strip" role="list">
            {coins.map((coin) => (
              <div className="premium-wallet-balance-chip" key={coin.key || coin.symbol} role="listitem">
                <span>{coin.symbol}</span>
                <strong>{coin.display_amount}</strong>
              </div>
            ))}
          </div>
        ) : (
          <p className="premium-wallet-balances-empty">{t('wallet_vault_balances_zero')}</p>
        )}
        {onRefreshWallet ? (
          <button
            className="vault-balances-refresh"
            disabled={refreshingWallet}
            onClick={onRefreshWallet}
            type="button"
          >
            {refreshingWallet ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
          </button>
        ) : null}
        {observationState === 'unavailable' ? (
          <p className="premium-wallet-balances-empty-hint">{t('wallet_vault_balances_unavailable_hint')}</p>
        ) : null}
      </div>
    </section>
  );
}

export function SafeNetworksSection({
  children,
  defaultOpen = false,
  open: controlledOpen,
  onOpenChange,
}) {
  const { t } = useLocale();
  const [internalOpen, setInternalOpen] = useState(defaultOpen);
  const open = controlledOpen ?? internalOpen;

  const toggleOpen = () => {
    const next = !open;
    if (onOpenChange) {
      onOpenChange(next);
      return;
    }

    setInternalOpen(next);
  };

  return (
    <section className="vault-safe-networks">
      <button
        aria-expanded={open}
        className="vault-safe-networks__toggle"
        onClick={toggleOpen}
        type="button"
      >
        <span>{t('wallet_safe_networks_title')}</span>
        <small>{open ? t('wallet_safe_networks_hide') : t('wallet_safe_networks_show')}</small>
      </button>
      {open ? <div className="vault-safe-networks__body">{children}</div> : null}
    </section>
  );
}
