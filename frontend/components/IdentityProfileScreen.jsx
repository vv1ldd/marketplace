'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { WALLET_BINDING_STATE } from '../lib/identity-wallets';
import { listBindingEvents } from '../lib/storefront-api';
import { readStoredVaultToken } from '../lib/vault-authority';
import { buildInstrumentCapabilityRows, readIdentityPaymentFlags } from '../lib/settlement-capabilities';
import { ConnectSettlementInstrumentCard, isExternalConnectInstrument } from './ConnectSettlementInstrumentCard';
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

function SettlementInstrumentRow({ walletBinding, t }) {
  const isManaged = walletBinding.bindingSource === 'managed';

  return (
    <article className="identity-instrument-row">
      <div className="identity-instrument-row__header">
        <strong>{walletBinding.label}</strong>
        <span className="identity-instrument-row__check" aria-hidden="true">✓</span>
      </div>
      <div className="identity-instrument-row__meta">
        <span>{isManaged ? t('identity_instrument_managed') : t('identity_instrument_connected')}</span>
        <span>
          {t('identity_instrument_receiving', {
            asset: instrumentReceiveAsset(walletBinding.key),
          })}
        </span>
      </div>
    </article>
  );
}

function ManagedInstrumentOption({
  walletBinding,
  onConnect,
  onCreateManaged,
  connectingKey,
  connectError,
  t,
}) {
  const isConnecting = connectingKey === walletBinding.key;
  const rowError = connectError?.key === walletBinding.key ? connectError.message : '';

  return (
    <article className="identity-add-instrument-option">
      <strong>{walletBinding.label}</strong>
      <p>{t('identity_add_managed_body')}</p>
      <div className="identity-add-instrument-option__actions">
        <button disabled={isConnecting} onClick={() => onCreateManaged(walletBinding.key)} type="button">
          {isConnecting ? t('wallet_create_safe_opening') : t('identity_add_create_managed')}
        </button>
        <button
          className="identity-account-secondary"
          disabled={isConnecting}
          onClick={() => onConnect(walletBinding.key)}
          type="button"
        >
          {isConnecting ? t('wallet_connect_opening') : t('identity_add_connect_existing')}
        </button>
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
  onConnect,
  onCreateManaged,
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
        && (wallet.canConnect || wallet.canCreateManaged),
    ),
    [futureWallets, visibleWallets],
  );

  const managedAddable = useMemo(
    () => addableWallets.filter((wallet) => wallet.canCreateManaged),
    [addableWallets],
  );

  const externalAddable = useMemo(
    () => addableWallets.filter((wallet) => isExternalConnectInstrument(wallet.key) && wallet.canConnect),
    [addableWallets],
  );

  const otherConnectable = useMemo(
    () => addableWallets.filter(
      (wallet) => !wallet.canCreateManaged && !isExternalConnectInstrument(wallet.key) && wallet.canConnect,
    ),
    [addableWallets],
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
            <strong>{t('identity_profile_ownership_connected')}</strong>
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
                key={walletBinding.key}
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
                <h3>{t('identity_add_section_managed')}</h3>
                <div className="identity-add-instrument-options">
                  {managedAddable.map((walletBinding) => (
                    <ManagedInstrumentOption
                      connectError={connectError}
                      connectingKey={connectingKey}
                      key={walletBinding.key}
                      onConnect={onConnect}
                      onCreateManaged={onCreateManaged}
                      t={t}
                      walletBinding={walletBinding}
                    />
                  ))}
                </div>
              </section>
            ) : null}
            {externalAddable.length ? (
              <section className="identity-add-instrument-section">
                <h3>{t('identity_add_section_connect')}</h3>
                <div className="identity-add-instrument-options">
                  {externalAddable.map((walletBinding) => (
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
            {otherConnectable.length ? (
              <section className="identity-add-instrument-section">
                <h3>{t('identity_add_section_other')}</h3>
                <div className="identity-add-instrument-options">
                  {otherConnectable.map((walletBinding) => (
                    <ConnectSettlementInstrumentCard
                      connectError={connectError}
                      connectNotice={connectNotice}
                      connectingKey={connectingKey}
                      key={walletBinding.key}
                      onConnect={onConnect}
                      onCreateManaged={onCreateManaged}
                      showManagedActions={walletBinding.canCreateManaged}
                      t={t}
                      walletBinding={walletBinding}
                    />
                  ))}
                </div>
              </section>
            ) : null}
            {!managedAddable.length && !externalAddable.length && !otherConnectable.length ? (
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
