'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { WALLET_BINDING_STATE } from '../lib/identity-wallets';
import { listBindingEvents } from '../lib/storefront-api';
import { readStoredVaultToken } from '../lib/vault-authority';
import { buildInstrumentCapabilityRows, readIdentityPaymentFlags } from '../lib/settlement-capabilities';
import { ConnectSettlementInstrumentCard } from './ConnectSettlementInstrumentCard';
import { SettlementCapabilityMatrix } from './SettlementCapabilityMatrix';
import { useLocale } from './LocaleProvider';

function identityLabel(identity = {}) {
  if (identity.username) return `@${identity.username}`;
  return identity.display_alias || identity.alias || identity.entity_l1_address || 'Vault identity';
}

function instrumentReceiveAsset(networkKey) {
  if (networkKey === 'bitcoin') return 'BTC';
  if (networkKey === 'solana') return 'SOL';
  if (networkKey === 'ton') return 'TON';
  return 'USDC';
}

function networkLabel(bindingKey) {
  if (!bindingKey) return '';
  return bindingKey.charAt(0).toUpperCase() + bindingKey.slice(1);
}

function formatIdentityEventWhen(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  const now = new Date();
  if (date.toDateString() === now.toDateString()) return 'Today';

  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function bindingEventLabel(event, t) {
  const network = networkLabel(event?.binding_key);
  switch (event?.event_type) {
    case 'wallet_bound':
      return t('identity_event_instrument_added', { network });
    case 'wallet_revoked':
      return t('identity_event_instrument_revoked', { network });
    case 'wallet_binding_failed':
      return t('identity_event_instrument_failed', { network });
    default:
      return event?.event_type || t('identity_event_unknown');
  }
}

function ownershipLabel(walletBinding, t) {
  return walletBinding.bindingSource === 'managed'
    ? t('identity_instrument_managed')
    : t('identity_instrument_connected');
}

function SettlementInstrumentRow({
  walletBinding,
  actionError,
  actingKey,
  onReplace,
  onRevoke,
  t,
}) {
  const [expanded, setExpanded] = useState(false);
  const [confirmAction, setConfirmAction] = useState('');
  const isManaged = walletBinding.bindingSource === 'managed';
  const isActing = actingKey === walletBinding.key;
  const rowError = actionError?.key === walletBinding.key ? actionError.message : '';

  return (
    <article className={`identity-instrument-row${expanded ? ' is-expanded' : ''}`}>
      <div className="identity-instrument-row__header">
        <strong>{walletBinding.label}</strong>
        <span className="identity-instrument-row__check" aria-hidden="true">✓</span>
      </div>
      <div className="identity-instrument-row__meta">
        <span>{ownershipLabel(walletBinding, t)}</span>
        <span>
          {t('identity_instrument_receiving', {
            asset: instrumentReceiveAsset(walletBinding.key),
          })}
        </span>
      </div>
      <div className="identity-instrument-row__actions">
        <button
          className="identity-account-secondary"
          onClick={() => setExpanded((current) => !current)}
          type="button"
        >
          {expanded ? t('identity_instrument_hide') : t('identity_instrument_view')}
        </button>
        {walletBinding.canCreateManaged ? (
          <button
            className="identity-account-secondary"
            disabled={isActing || !walletBinding.bindingId}
            onClick={() => setConfirmAction((current) => (current === 'replace' ? '' : 'replace'))}
            type="button"
          >
            {t('identity_instrument_replace')}
          </button>
        ) : null}
        <button
          className="identity-account-secondary identity-instrument-row__danger"
          disabled={isActing || !walletBinding.bindingId}
          onClick={() => setConfirmAction((current) => (current === 'revoke' ? '' : 'revoke'))}
          type="button"
        >
          {t('identity_instrument_revoke')}
        </button>
      </div>
      {expanded ? (
        <div className="identity-instrument-row__details">
          <p>
            <span>{t('identity_instrument_details_ownership')}</span>
            <strong>{ownershipLabel(walletBinding, t)}</strong>
          </p>
          <p>
            <span>{t('identity_instrument_details_address')}</span>
            <code>{walletBinding.address || '—'}</code>
          </p>
          {isManaged ? (
            <p className="identity-instrument-row__hint">{t('identity_instrument_managed_hint')}</p>
          ) : (
            <p className="identity-instrument-row__hint">{t('identity_instrument_external_hint')}</p>
          )}
        </div>
      ) : null}
      {confirmAction === 'revoke' ? (
        <div className="identity-instrument-row__confirm">
          <p>{t('identity_instrument_revoke_confirm', { network: walletBinding.label })}</p>
          <div className="identity-instrument-row__actions">
            <button
              className="identity-account-secondary"
              disabled={isActing}
              onClick={() => setConfirmAction('')}
              type="button"
            >
              {t('identity_instrument_cancel')}
            </button>
            <button
              className="identity-instrument-row__danger"
              disabled={isActing}
              onClick={() => onRevoke(walletBinding)}
              type="button"
            >
              {isActing ? t('identity_instrument_revoking') : t('identity_instrument_revoke')}
            </button>
          </div>
        </div>
      ) : null}
      {confirmAction === 'replace' ? (
        <div className="identity-instrument-row__confirm">
          <p>{t('identity_instrument_replace_confirm', { network: walletBinding.label })}</p>
          <div className="identity-instrument-row__actions">
            <button
              className="identity-account-secondary"
              disabled={isActing}
              onClick={() => setConfirmAction('')}
              type="button"
            >
              {t('identity_instrument_cancel')}
            </button>
            <button
              disabled={isActing}
              onClick={() => onReplace(walletBinding)}
              type="button"
            >
              {isActing ? t('identity_instrument_replacing') : t('identity_instrument_replace_cta')}
            </button>
          </div>
        </div>
      ) : null}
      {rowError ? <p className="identity-send-error">{rowError}</p> : null}
    </article>
  );
}

function ManagedInstrumentOption({
  walletBinding,
  onCreateManaged,
  onImportManaged,
  connectingKey,
  connectError,
  t,
}) {
  const isConnecting = connectingKey === walletBinding.key;
  const rowError = connectError?.key === walletBinding.key ? connectError.message : '';

  return (
    <article className="identity-add-instrument-option">
      <strong>{walletBinding.label}</strong>
      <div className="identity-add-instrument-option__paths">
        <div className="identity-add-instrument-option__path">
          <p className="identity-add-instrument-option__path-title">{t('identity_add_create_managed')}</p>
          <p className="identity-add-instrument-option__path-body">{t('identity_add_create_managed_hint')}</p>
          <button disabled={isConnecting} onClick={() => onCreateManaged(walletBinding.key)} type="button">
            {isConnecting ? t('wallet_create_safe_opening') : t('identity_add_create_managed_cta')}
          </button>
        </div>
        <div className="identity-add-instrument-option__path identity-add-instrument-option__path--migrate">
          <p className="identity-add-instrument-option__path-title">{t('identity_add_import_managed')}</p>
          <p className="identity-add-instrument-option__path-body">{t('identity_add_import_managed_hint')}</p>
          <button
            className="identity-account-secondary"
            disabled={isConnecting}
            onClick={() => onImportManaged(walletBinding.key)}
            type="button"
          >
            {isConnecting ? t('identity_import_seed_importing') : t('identity_add_import_managed_cta')}
          </button>
        </div>
      </div>
      {rowError ? <p className="identity-send-error">{rowError}</p> : null}
    </article>
  );
}

export function IdentityProfileScreen({
  identity,
  visibleWallets = [],
  futureWallets = [],
  walletBindings = [],
  identityPaymentFlags = {},
  connectNotice,
  connectPhase,
  connectingKey,
  connectError,
  instrumentActionError,
  instrumentActingKey,
  legacyConnectEnabled = false,
  onConnect,
  onCreateManaged,
  onImportManaged,
  onRevokeInstrument,
  onReplaceInstrument,
  variant = 'profile',
}) {
  const { t } = useLocale();
  const showStatus = variant === 'profile';
  const showEvents = variant === 'profile';
  const showCapabilityMatrix = variant === 'profile';
  const [showAddInstrument, setShowAddInstrument] = useState(variant === 'networks');
  const [bindingEvents, setBindingEvents] = useState([]);
  const [eventsLoading, setEventsLoading] = useState(false);
  const [eventsError, setEventsError] = useState('');

  const connectedInstruments = useMemo(
    () => visibleWallets.filter((wallet) => wallet.bindingState === WALLET_BINDING_STATE.CONNECTED),
    [visibleWallets],
  );

  const bindingByKey = useMemo(
    () => new Map(
      walletBindings
        .filter((entry) => entry?.binding_key)
        .map((entry) => [entry.binding_key, entry]),
    ),
    [walletBindings],
  );

  const capabilityRows = useMemo(
    () => buildInstrumentCapabilityRows(connectedInstruments, {
      ...identityPaymentFlags,
      bindingByKey,
    }),
    [bindingByKey, connectedInstruments, identityPaymentFlags],
  );

  const addableWallets = useMemo(
    () => [...visibleWallets, ...futureWallets].filter(
      (wallet) => wallet.bindingState !== WALLET_BINDING_STATE.CONNECTED
        && (wallet.canCreateManaged || (legacyConnectEnabled && wallet.canConnect)),
    ),
    [futureWallets, legacyConnectEnabled, visibleWallets],
  );

  const managedAddable = useMemo(
    () => addableWallets.filter((wallet) => wallet.canCreateManaged),
    [addableWallets],
  );

  const legacyConnectAddable = useMemo(
    () => (legacyConnectEnabled
      ? addableWallets.filter((wallet) => wallet.canConnect && !wallet.canCreateManaged)
      : []),
    [addableWallets, legacyConnectEnabled],
  );

  const loadBindingEvents = useCallback(async () => {
    const token = readStoredVaultToken();
    if (!token) {
      setBindingEvents([]);
      return;
    }

    setEventsLoading(true);
    setEventsError('');
    try {
      const payload = await listBindingEvents(token, { limit: 25 });
      setBindingEvents(payload?.items || []);
    } catch (exception) {
      setEventsError(exception?.message || t('identity_events_error'));
      setBindingEvents([]);
    } finally {
      setEventsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadBindingEvents();
  }, [loadBindingEvents]);

  useEffect(() => {
    if (connectNotice) {
      loadBindingEvents();
    }
  }, [connectNotice, loadBindingEvents]);

  return (
    <div className={`identity-profile-screen${variant === 'networks' ? ' identity-profile-screen--networks' : ''}`}>
      {showStatus ? (
        <header className="identity-profile-screen__header">
          <strong>{identityLabel(identity)}</strong>
          <span>{t('identity_profile_title')}</span>
        </header>
      ) : null}

      {showStatus ? (
        <section className="identity-profile-status">
          <div>
            <span>{t('identity_profile_status_label')}</span>
            <strong>{t('identity_profile_status_active')}</strong>
          </div>
          <div>
            <span>{t('identity_profile_ownership_label')}</span>
            <strong>{t('identity_profile_ownership_vault')}</strong>
          </div>
          <div>
            <span>{t('identity_profile_economic_state_label')}</span>
            <strong>{t('identity_profile_economic_state_connected')}</strong>
          </div>
        </section>
      ) : null}

      <section className="identity-profile-instruments">
        {variant === 'networks' ? null : (
          <div className="identity-account-recent__header">
            <span>{t('identity_instruments_title')}</span>
          </div>
        )}
        {connectedInstruments.length ? (
          <div className="identity-instrument-list">
            {connectedInstruments.map((walletBinding) => (
              <SettlementInstrumentRow
                actionError={instrumentActionError}
                actingKey={instrumentActingKey}
                key={walletBinding.key}
                onReplace={onReplaceInstrument}
                onRevoke={onRevokeInstrument}
                t={t}
                walletBinding={walletBinding}
              />
            ))}
          </div>
        ) : (
          <p className="premium-wallet-balances-empty">{t('identity_instruments_empty')}</p>
        )}

        {showCapabilityMatrix ? (
          <SettlementCapabilityMatrix rows={capabilityRows} t={t} />
        ) : null}

        {variant === 'networks' ? null : (
          <button
            className="identity-add-instrument-toggle"
            onClick={() => setShowAddInstrument((current) => !current)}
            type="button"
          >
            {showAddInstrument ? t('identity_add_instrument_hide') : t('identity_add_instrument_show')}
          </button>
        )}

        {showAddInstrument ? (
          <div className="identity-add-instrument-panel">
            <header>
              <strong>{t('identity_add_instrument_title')}</strong>
              <p>{t('identity_add_instrument_body')}</p>
            </header>
            {connectPhase ? <p className="premium-wallet-bindings__phase">{connectPhase}</p> : null}
            {managedAddable.length ? (
              <section className="identity-add-instrument-section">
                <div className="identity-add-instrument-options">
                  {managedAddable.map((walletBinding) => (
                    <ManagedInstrumentOption
                      connectError={connectError}
                      connectingKey={connectingKey}
                      key={walletBinding.key}
                      onCreateManaged={onCreateManaged}
                      onImportManaged={onImportManaged}
                      t={t}
                      walletBinding={walletBinding}
                    />
                  ))}
                </div>
              </section>
            ) : null}
            {legacyConnectAddable.length ? (
              <section className="identity-add-instrument-section">
                <h3>{t('identity_add_section_legacy_connect')}</h3>
                <div className="identity-add-instrument-options">
                  {legacyConnectAddable.map((walletBinding) => (
                    <ConnectSettlementInstrumentCard
                      connectError={connectError}
                      connectNotice={connectNotice}
                      connectingKey={connectingKey}
                      key={walletBinding.key}
                      onConnect={onConnect}
                      t={t}
                      walletBinding={walletBinding}
                    />
                  ))}
                </div>
              </section>
            ) : null}
            {!managedAddable.length && !legacyConnectAddable.length ? (
              <p className="premium-wallet-balances-empty">{t('identity_add_instrument_none')}</p>
            ) : null}
          </div>
        ) : null}
      </section>

      {showEvents ? (
      <section className="identity-profile-events">
        <div className="identity-account-recent__header">
          <span>{t('identity_events_title')}</span>
          <button disabled={eventsLoading} onClick={loadBindingEvents} type="button">
            {eventsLoading ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
          </button>
        </div>
        {eventsError ? <p className="identity-send-error">{eventsError}</p> : null}
        {bindingEvents.length ? (
          <div className="identity-event-list">
            {bindingEvents.map((event) => (
              <div className="identity-event-row" key={event.id}>
                <span>{bindingEventLabel(event, t)}</span>
                <small>{formatIdentityEventWhen(event.occurred_at)}</small>
              </div>
            ))}
          </div>
        ) : (
          !eventsLoading && <p className="premium-wallet-balances-empty">{t('identity_events_empty')}</p>
        )}
      </section>
      ) : null}
    </div>
  );
}
