'use client';

import { walletConnectIntroKey } from '../lib/identity-wallets';

const EXTERNAL_CONNECT_KEYS = new Set(['bitcoin', 'solana', 'ton']);

export function isExternalConnectInstrument(networkKey) {
  return EXTERNAL_CONNECT_KEYS.has(networkKey);
}

export function ConnectSettlementInstrumentCard({
  walletBinding,
  onConnect,
  onCreateManaged,
  onImportManaged,
  connectingKey,
  connectError,
  connectNotice,
  showManagedActions = false,
  t,
}) {
  const isConnecting = connectingKey === walletBinding.key;
  const rowError = connectError?.key === walletBinding.key ? connectError.message : '';
  const rowNotice = connectNotice?.key === walletBinding.key ? connectNotice.message : '';
  const introKey = walletConnectIntroKey(walletBinding.key);

  return (
    <article className={`identity-connect-card identity-connect-card--${walletBinding.key}`}>
      <header className="identity-connect-card__header">
        <strong>{walletBinding.label}</strong>
        <span className="identity-connect-card__badge">{t('identity_instrument_connect_only')}</span>
      </header>
      <p>{t(introKey)}</p>
      {showManagedActions ? (
        <div className="identity-add-instrument-option__actions">
          <button disabled={isConnecting} onClick={() => onCreateManaged(walletBinding.key)} type="button">
            {isConnecting ? t('wallet_create_safe_opening') : t('identity_add_create_managed_cta')}
          </button>
          <button
            className="identity-account-secondary"
            disabled={isConnecting}
            onClick={() => onImportManaged(walletBinding.key)}
            type="button"
          >
            {isConnecting ? t('identity_import_seed_importing') : t('identity_add_import_managed_cta')}
          </button>
        </div>
      ) : (
        <button
          className="identity-account-primary"
          disabled={isConnecting || !walletBinding.canConnect}
          onClick={() => onConnect(walletBinding.key)}
          type="button"
        >
          {isConnecting ? t('wallet_connect_signing') : t('identity_connect_instrument_cta')}
        </button>
      )}
      {rowNotice ? <p className="vault-safe-provisioning__notice">{rowNotice}</p> : null}
      {rowError ? <p className="identity-send-error">{rowError}</p> : null}
    </article>
  );
}
