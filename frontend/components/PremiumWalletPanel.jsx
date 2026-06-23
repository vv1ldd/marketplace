'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  resolveIdentityWalletModel,
  shortenAddress,
  shouldShowSafeProvisioningShell,
  shouldShowSafeDashboard,
  vaultBalanceObservationState,
  visibleWalletBindings,
  WALLET_BINDING_STATE,
  walletCapabilityNote,
  walletCapabilityNoteTone,
  walletConnectIntroKey,
  walletCoins,
  resolveDefaultProvisionNetworkKey,
  resolvePolygonWalletEntry,
} from '../lib/identity-wallets';
import { buildBitcoinReceiveQrDataUrl, buildPolygonUsdcReceiveQrDataUrl } from '../lib/identity-wallet-qr';
import { fetchWalletBundle, importManagedWalletBinding, provisionManagedWalletBinding, refreshStorefrontVaultToken, revokeWalletBinding } from '../lib/storefront-api';
import { readIdentityPaymentFlags } from '../lib/settlement-capabilities';
import { clearVaultAuthorityState, readStoredVaultToken } from '../lib/vault-authority';
import { connectWalletBinding, isEvmWalletBindingKey, WalletConnectError } from '../lib/wallet-connect';
import { EvmWalletPicker } from './EvmWalletPicker';
import { ImportSeedModal } from './ImportSeedModal';
import { GlossaryHint } from './GlossaryHint';
import { IdentityAccountPanel } from './IdentityAccountPanel';
import { VaultQrIcon, VaultShieldIcon } from './IdentityVaultIcons';
import { MeanlyLoadingMark } from './MeanlyLoadingMark';
import { useLocale } from './LocaleProvider';
import {
  SafeNetworksSection,
  SafeWelcomeCard,
} from './VaultSafeShell';

function identityLabel(identity = {}) {
  if (identity.username) return `@${identity.username}`;
  return identity.display_alias || identity.alias || identity.entity_l1_address || 'Vault identity';
}

function bindingStateLabel(state, t) {
  switch (state) {
    case WALLET_BINDING_STATE.CONNECTED:
      return t('wallet_binding_connected');
    case WALLET_BINDING_STATE.CONNECT:
      return t('wallet_binding_connect');
    case WALLET_BINDING_STATE.PENDING:
      return t('wallet_binding_pending');
    default:
      return t('wallet_binding_coming_soon');
  }
}

function WalletBindingStatus({ state, label }) {
  return (
    <span className={`premium-wallet-binding__status premium-wallet-binding__status--${state}`}>
      {label}
    </span>
  );
}

function WalletBindingDetail({ walletBinding, t, compact = false, showAssets = true, showReceiveQr = false }) {
  const preview = walletBinding.preview;
  const coins = walletCoins(preview);

  return (
    <div className={`premium-wallet-binding__detail ${compact ? 'premium-wallet-binding__detail--compact' : ''}`}>
      <div className="premium-wallet-binding__detail-row">
        <span>{t('wallet_binding_address')}</span>
        <strong>{walletBinding.address ? shortenAddress(walletBinding.address, 10, 8) : t('wallet_binding_no_address')}</strong>
      </div>
      {walletBinding.address ? <code className="premium-wallet-binding__address">{walletBinding.address}</code> : null}

      {showReceiveQr && walletBinding.key === 'polygon' && walletBinding.address ? (
        <VaultReceiveQr
          address={walletBinding.address}
          altKey="wallet_identity_qr_alt"
          buildQrDataUrl={buildPolygonUsdcReceiveQrDataUrl}
          hintKey="wallet_identity_qr_hint"
          labelKey="wallet_identity_qr"
        />
      ) : null}

      {showReceiveQr && walletBinding.key === 'bitcoin' && walletBinding.address ? (
        <VaultReceiveQr
          address={walletBinding.address}
          altKey="wallet_bitcoin_qr_alt"
          buildQrDataUrl={buildBitcoinReceiveQrDataUrl}
          hintKey="wallet_bitcoin_qr_hint"
          labelKey="wallet_bitcoin_qr"
        />
      ) : null}

      {showAssets && coins.length ? (
        <div className="premium-wallet-asset-list">
          <span className="premium-wallet-binding__detail-label">{t('wallet_binding_assets')}</span>
          {coins.map((coin) => (
            <div className="premium-wallet-asset-row" key={coin.key || coin.symbol}>
              <span>{coin.symbol}</span>
              <strong>{coin.display_amount}</strong>
              {!compact && coin.name ? <small>{coin.name}</small> : null}
            </div>
          ))}
        </div>
      ) : null}

      {preview?.capabilities ? (
        <p className={`premium-wallet-capability-note premium-wallet-capability-note--${walletCapabilityNoteTone(preview.capabilities.next_action)}`}>
          {walletCapabilityNote(
            {
              nextAction: preview.capabilities.next_action,
              networkKey: walletBinding.key,
            },
            t,
          )}
        </p>
      ) : null}
    </div>
  );
}

function WalletBindingRow({
  walletBinding,
  isActive,
  onSelect,
  onConnect,
  onCreateManaged,
  onImportManaged,
  legacyConnectEnabled = false,
  connectingKey,
  connectError,
  connectNotice,
  suppressAssets = false,
  suppressManagedProvisioning = false,
  showReceiveQr = false,
  t,
}) {
  const state = walletBinding.bindingState;
  const statusLabel = bindingStateLabel(state, t);
  const canExpand = state === WALLET_BINDING_STATE.CONNECTED && Boolean(walletBinding.preview);
  const showManagedActions = walletBinding.canCreateManaged && !suppressManagedProvisioning;
  const showLegacyConnect = legacyConnectEnabled && walletBinding.canConnect && !showManagedActions;
  const showConnectPanel = showLegacyConnect || showManagedActions || state === WALLET_BINDING_STATE.PENDING;
  const isInteractive = canExpand || showConnectPanel;
  const isConnecting = connectingKey === walletBinding.key;
  const rowError = connectError?.key === walletBinding.key ? connectError.message : '';
  const rowNotice = connectNotice?.key === walletBinding.key ? connectNotice.message : '';

  return (
    <article className={`premium-wallet-binding ${isActive ? 'is-active' : ''} ${isConnecting ? 'is-connecting' : ''}`}>
      <button
        className="premium-wallet-binding__row"
        disabled={(!isInteractive && !canExpand) || isConnecting}
        onClick={() => {
          if (isInteractive || canExpand) {
            onSelect(isActive ? null : walletBinding.key);
          }
        }}
        type="button"
      >
        <span className="premium-wallet-binding__label">{walletBinding.label}</span>
        <WalletBindingStatus label={statusLabel} state={state} />
      </button>

      {isActive && canExpand ? (
        <WalletBindingDetail
          compact
          showAssets={!suppressAssets}
          showReceiveQr={showReceiveQr}
          walletBinding={walletBinding}
          t={t}
        />
      ) : null}

      {showConnectPanel ? (
        <div className="premium-wallet-binding__connect">
          <p>{t(showManagedActions ? 'wallet_polygon_attach_intro' : walletConnectIntroKey(walletBinding.key))}</p>
          {showManagedActions ? (
            <div className="premium-wallet-binding__connect-actions">
              <button
                disabled={isConnecting}
                onClick={() => onCreateManaged(walletBinding.key)}
                type="button"
              >
                {isConnecting ? t('wallet_create_safe_opening') : t('wallet_create_safe_cta')}
              </button>
              <button
                className="premium-wallet-binding__connect-secondary"
                disabled={isConnecting}
                onClick={() => onImportManaged(walletBinding.key)}
                type="button"
              >
                {isConnecting ? t('identity_import_seed_importing') : t('identity_import_seed_open')}
              </button>
            </div>
          ) : showLegacyConnect ? (
            <button
              disabled={isConnecting}
              onClick={() => onConnect(walletBinding.key)}
              type="button"
            >
              {isConnecting ? t('wallet_connect_opening') : t('wallet_connect_cta')}
            </button>
          ) : state === WALLET_BINDING_STATE.COMING_SOON ? (
            <button disabled type="button">{t('wallet_binding_coming_soon')}</button>
          ) : null}
          {rowNotice ? <small className="premium-wallet-binding__notice">{rowNotice}</small> : null}
          {rowError ? <small className="premium-wallet-binding__error">{rowError}</small> : null}
        </div>
      ) : null}
    </article>
  );
}

function coinAmount(coin = {}) {
  const amount = Number(coin.amount ?? 0);
  return Number.isFinite(amount) ? amount : 0;
}

function hasPositiveBalances(coins = []) {
  return coins.some((coin) => coinAmount(coin) > 0);
}

function VaultReceiveQr({
  address,
  buildQrDataUrl,
  labelKey,
  altKey,
  hintKey,
}) {
  const { t } = useLocale();
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
      const dataUrl = qrDataUrl || await buildQrDataUrl(address);
      if (dataUrl) {
        setQrDataUrl(dataUrl);
        setQrOpen(true);
      }
    } catch {
      setQrOpen(false);
    } finally {
      setQrLoading(false);
    }
  }, [address, buildQrDataUrl, qrDataUrl, qrOpen]);

  if (!address) {
    return null;
  }

  return (
    <div className="vault-identity-qr-slot">
      <div className="vault-identity-qr-slot__anchor">
        <button
          aria-expanded={qrOpen}
          aria-label={t(labelKey)}
          className={`vault-identity-address__action vault-identity-address__action--qr${qrOpen ? ' is-active' : ''}`}
          disabled={qrLoading}
          onClick={toggleQr}
          title={t(labelKey)}
          type="button"
        >
          <VaultQrIcon />
        </button>
        {qrOpen && qrDataUrl ? (
          <div className="vault-identity-address__qr-popover" role="dialog">
            <img alt={t(altKey)} src={qrDataUrl} />
            <p>{t(hintKey)}</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function VaultHeroHeader({
  kicker,
  title = '',
  showTitle = true,
  identityLine = '',
  address = '',
  copyableAddress = false,
  placeholder = false,
  showIdentityLine = true,
  settlementSlot = null,
  useVaultSettlementLayout = false,
}) {
  const showIdentitySection = showIdentityLine || Boolean(address) || placeholder;
  const isVaultSettlementLayout = useVaultSettlementLayout || (copyableAddress && Boolean(settlementSlot));

  return (
    <header className={`premium-wallet-hero${placeholder ? ' premium-wallet-hero--placeholder' : ''}${isVaultSettlementLayout ? ' premium-wallet-hero--identity-compact' : ''}`.trim()}>
      <div className="premium-wallet-hero__icon" aria-hidden="true">
        <VaultShieldIcon />
      </div>
      <div className="premium-wallet-hero__copy">
        <div className={`premium-wallet-hero__heading${showTitle ? '' : ' premium-wallet-hero__heading--compact'}`}>
          <span>{kicker}</span>
          {showTitle && title ? <strong>{title}</strong> : null}
        </div>
        {isVaultSettlementLayout ? (
          settlementSlot
        ) : showIdentitySection ? (
          <div className="premium-wallet-hero__identity">
            {placeholder ? (
              <>
                {showIdentityLine ? (
                  <strong className="premium-wallet-placeholder-line premium-wallet-placeholder-line--md" />
                ) : null}
                <span className="premium-wallet-placeholder-line premium-wallet-placeholder-line--sm" />
              </>
            ) : (
              <>
                {showIdentityLine && identityLine ? <strong>{identityLine}</strong> : null}
                {address ? <code>{address}</code> : null}
              </>
            )}
          </div>
        ) : null}
      </div>
    </header>
  );
}

function vaultBalancesEmptyCopy(t, observationState = 'none') {
  if (observationState === 'unavailable') {
    return {
      message: t('wallet_vault_balances_unavailable'),
      hint: t('wallet_vault_balances_unavailable_hint'),
    };
  }

  if (observationState === 'zero') {
    return {
      message: t('wallet_vault_balances_zero'),
      hint: t('wallet_vault_balances_zero_hint'),
    };
  }

  return {
    message: t('wallet_vault_balances_empty'),
    hint: null,
  };
}

function WalletBalancesSection({
  coins,
  t,
  variant = 'wallet',
  skipEmptyState = false,
  onRefresh = null,
  refreshing = false,
  observationState = 'none',
}) {
  const isVault = variant === 'vault';

  if (!isVault) {
    if (!coins.length || !hasPositiveBalances(coins)) {
      return null;
    }
  } else if (!coins.length || !hasPositiveBalances(coins)) {
    if (skipEmptyState) {
      return null;
    }

    const emptyCopy = vaultBalancesEmptyCopy(t, observationState);

    return (
      <div className="premium-wallet-body premium-wallet-body--vault-balances">
        <p className="premium-wallet-balances-empty">{emptyCopy.message}</p>
        {emptyCopy.hint ? <p className="premium-wallet-balances-empty-hint">{emptyCopy.hint}</p> : null}
        {onRefresh ? (
          <button
            className="vault-balances-refresh"
            disabled={refreshing}
            onClick={onRefresh}
            type="button"
          >
            {refreshing ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
          </button>
        ) : null}
      </div>
    );
  }

  return (
    <div className="premium-wallet-body premium-wallet-body--vault-balances">
      <div aria-label={t('wallet_summary_balances')} className="premium-wallet-balance-strip" role="list">
        {coins.map((coin) => (
          <div className="premium-wallet-balance-chip" key={coin.key || coin.symbol} role="listitem">
            <span>{coin.symbol}</span>
            <strong>{coin.display_amount}</strong>
          </div>
        ))}
      </div>
      {isVault && onRefresh ? (
        <button
          className="vault-balances-refresh"
          disabled={refreshing}
          onClick={onRefresh}
          type="button"
        >
          {refreshing ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
        </button>
      ) : null}
    </div>
  );
}

function walletPanelCopy(t, variant = 'wallet') {
  const isVault = variant === 'vault';

  return {
    kicker: isVault ? t('wallet_vault_kicker') : t('wallet_shell_title'),
    hint: isVault ? t('wallet_identity_hint') : t('wallet_shell_hint'),
    showTitle: !isVault,
    title: t('wallet_identity_name'),
  };
}

function WalletHero({
  model,
  t,
  variant = 'wallet',
  onRefreshWallet,
  refreshingWallet = false,
}) {
  const copy = walletPanelCopy(t, variant);
  const isVault = variant === 'vault';

  return (
    <>
      <VaultHeroHeader
        identityLine={identityLabel(model.identity)}
        kicker={(
          <>
            {copy.kicker}
            <GlossaryHint>{copy.hint}</GlossaryHint>
          </>
        )}
        showIdentityLine
        showTitle={copy.showTitle}
        title={copy.title}
      />

      <WalletBalancesSection
        coins={model.summaryCoins}
        observationState={isVault ? vaultBalanceObservationState(model.wallets) : 'none'}
        onRefresh={isVault ? onRefreshWallet : null}
        refreshing={refreshingWallet}
        t={t}
        variant={variant}
      />
    </>
  );
}

function WalletHeroFromIdentity({ identity, t, variant = 'wallet' }) {
  const address = identity?.entity_l1_address || '';
  const copy = walletPanelCopy(t, variant);
  const isVault = variant === 'vault';

  return (
    <VaultHeroHeader
      address={isVault ? '' : (address || '')}
      copyableAddress={isVault}
      identityLine={identityLabel(identity)}
      kicker={(
        <>
          {copy.kicker}
          <GlossaryHint>{copy.hint}</GlossaryHint>
        </>
      )}
      showIdentityLine
      showTitle={copy.showTitle}
      title={copy.title}
    />
  );
}

function WalletHeroPlaceholder({ t, variant = 'wallet' }) {
  const copy = walletPanelCopy(t, variant);

  return (
    <VaultHeroHeader
      kicker={copy.kicker}
      showIdentityLine={false}
      showTitle={copy.showTitle}
      title={copy.title}
    />
  );
}

function VaultWalletLoadingShell({ status = '', t, variant = 'wallet' }) {
  const isVault = variant === 'vault';
  const loadingLabel = status || (isVault ? t('wallet_vault_opening') : t('wallet_shell_loading'));

  return (
    <div className={`premium-wallet-body premium-wallet-body--loading${isVault ? ' premium-wallet-body--vault-opening' : ''}`}>
      <MeanlyLoadingMark label={loadingLabel} size={isVault ? 'md' : 'sm'} />
    </div>
  );
}

export function VaultWalletContent({
  wallet,
  vaultIdentity = null,
  status = '',
  error = '',
  isVaultOpen = false,
  isLoading = false,
  showOpenAction = true,
  onRefreshWallet,
  onRefreshWalletAssets,
  variant = 'wallet',
}) {
  const isVaultVariant = variant === 'vault';
  // UI normalization boundary: raw wallet payload may be null during loading.
  // Derive safe view-model fields here; presentation children must not dereference `model` directly.
  const { t } = useLocale();
  const model = useMemo(() => (wallet ? resolveIdentityWalletModel(wallet) : null), [wallet]);
  const identityPaymentFlags = useMemo(
    () => (wallet ? readIdentityPaymentFlags(wallet) : {}),
    [wallet],
  );
  const identity = model?.identity ?? null;
  const futureWalletBindings = model?.futureWallets ?? [];
  const summaryCoins = model?.summaryCoins ?? [];
  const walletBindings = model?.walletBindings ?? [];
  const managedWalletsEnabled = model?.managedWalletsEnabled ?? false;
  const autoProvisionOnVault = model?.autoProvisionOnVault ?? false;
  const managedNetworkKeys = model?.managedNetworkKeys;
  const legacyConnectEnabled = model?.legacyConnectEnabled ?? false;
  const visibleWallets = model ? visibleWalletBindings(model.wallets) : [];
  const polygonWallet = model ? resolvePolygonWalletEntry(model.wallets) : null;
  const [activeWalletKey, setActiveWalletKey] = useState(null);
  const [showFutureNetworks, setShowFutureNetworks] = useState(false);
  const [connectingKey, setConnectingKey] = useState(null);
  const [connectError, setConnectError] = useState(null);
  const [connectNotice, setConnectNotice] = useState(null);
  const [connectPhase, setConnectPhase] = useState('');
  const [refreshingWallet, setRefreshingWallet] = useState(false);
  const [evmPickerRequest, setEvmPickerRequest] = useState(null);
  const [importSeedTarget, setImportSeedTarget] = useState(null);
  const [importSeedError, setImportSeedError] = useState('');
  const [instrumentActingKey, setInstrumentActingKey] = useState(null);
  const [instrumentActionError, setInstrumentActionError] = useState(null);

  const selectEvmProvider = useCallback((providers) => {
    return new Promise((resolve, reject) => {
      setEvmPickerRequest({ providers, resolve, reject });
    });
  }, []);

  const dismissEvmPicker = useCallback((rejectError = null) => {
    setEvmPickerRequest((current) => {
      if (current && rejectError) {
        current.reject(rejectError);
      }

      return null;
    });
  }, []);

  useEffect(() => {
    if (!model) {
      setActiveWalletKey(null);
      setShowFutureNetworks(false);
    }
  }, [model]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined;
    }

    let cancelled = false;

    import('../lib/evm-wallet-discovery').then(({ warmEvmProviderDiscovery }) => {
      if (!cancelled) {
        warmEvmProviderDiscovery();
      }
    });

    if (window.ethereum) {
      import('../lib/metamask-multichain-bootstrap').then(({ ensureMetaMaskMultichainRegistered }) => {
        if (!cancelled) {
          ensureMetaMaskMultichainRegistered();
        }
      });
    }

    return () => {
      cancelled = true;
    };
  }, []);

  const handleConnect = useCallback(async (bindingKey) => {
    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setConnectError({ key: bindingKey, message: t('wallet_connect_session_expired') });
        return;
      }
    }

    setConnectingKey(bindingKey);
    setConnectError(null);
    setConnectNotice(null);
    setConnectPhase(t('wallet_connect_opening'));
    setActiveWalletKey(bindingKey);

    const runConnect = async (activeToken) => {
      await connectWalletBinding({
        token: activeToken,
        bindingKey,
        onConnectPhase: (phaseKey) => setConnectPhase(t(phaseKey)),
        selectEvmProvider: isEvmWalletBindingKey(bindingKey) ? selectEvmProvider : undefined,
      });
      setConnectPhase(t('wallet_connect_finishing'));
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      setConnectNotice({
        key: bindingKey,
        message: variant === 'vault' && identity
          ? t('identity_instrument_added', { alias: identityLabel(identity) })
          : t('wallet_connect_success'),
      });
      setActiveWalletKey(bindingKey);
    };

    try {
      await runConnect(token);
    } catch (exception) {
      const status = exception?.cause?.status ?? exception?.status;
      if (status === 401 || status === 403) {
        try {
          const freshToken = await refreshStorefrontVaultToken();
          await runConnect(freshToken);
          return;
        } catch (retryException) {
          const message = retryException instanceof WalletConnectError
            ? retryException.userMessage
            : retryException?.message || t('wallet_connect_session_expired');
          setConnectError({ key: bindingKey, message });
          return;
        }
      }

      const message = exception instanceof WalletConnectError
        ? (exception.code === 'no_wallet'
          ? t('wallet_connect_no_wallet')
          : exception.userMessage)
        : exception?.message || t('wallet_shell_error');
      setConnectError({ key: bindingKey, message });
    } finally {
      setConnectingKey(null);
      setConnectPhase('');
    }
  }, [identity, onRefreshWallet, selectEvmProvider, t, variant]);

  const handleActivateVault = useCallback(async () => {
    const bindingKey = resolveDefaultProvisionNetworkKey(managedNetworkKeys);
    setConnectingKey(bindingKey);
    setConnectError(null);
    setConnectNotice(null);
    setConnectPhase(t('wallet_create_safe_opening'));
    setActiveWalletKey(bindingKey);

    try {
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      setConnectNotice({
        key: bindingKey,
        message: variant === 'vault' && identity
          ? t('identity_instrument_added', { alias: identityLabel(identity) })
          : t('wallet_create_safe_success'),
      });
      setActiveWalletKey(bindingKey);
    } catch (exception) {
      setConnectError({
        key: bindingKey,
        message: exception?.message || t('wallet_shell_error'),
      });
    } finally {
      setConnectingKey(null);
      setConnectPhase('');
    }
  }, [identity, managedNetworkKeys, onRefreshWallet, t, variant]);

  const handleCreateManaged = useCallback(async (bindingKey) => {
    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setConnectError({ key: bindingKey, message: t('wallet_connect_session_expired') });
        return;
      }
    }

    setConnectingKey(bindingKey);
    setConnectError(null);
    setConnectNotice(null);
    setConnectPhase(t('wallet_create_safe_opening'));
    setActiveWalletKey(bindingKey);

    try {
      await provisionManagedWalletBinding(token, bindingKey);
      setConnectPhase(t('wallet_connect_finishing'));
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      setConnectNotice({
        key: bindingKey,
        message: variant === 'vault' && identity
          ? t('identity_instrument_added', { alias: identityLabel(identity) })
          : t('wallet_create_safe_success'),
      });
      setActiveWalletKey(bindingKey);
    } catch (exception) {
      const status = exception?.cause?.status ?? exception?.status;
      if (status === 401 || status === 403) {
        try {
          const freshToken = await refreshStorefrontVaultToken();
          await provisionManagedWalletBinding(freshToken, bindingKey);
          if (onRefreshWallet) {
            await onRefreshWallet();
          }
          setConnectNotice({
            key: bindingKey,
            message: variant === 'vault' && identity
              ? t('identity_instrument_added', { alias: identityLabel(identity) })
              : t('wallet_create_safe_success'),
          });
          setActiveWalletKey(bindingKey);
          return;
        } catch (retryException) {
          const message = retryException?.message || t('wallet_connect_session_expired');
          setConnectError({ key: bindingKey, message });
          return;
        }
      }

      const message = exception?.message || t('wallet_shell_error');
      setConnectError({ key: bindingKey, message });
    } finally {
      setConnectingKey(null);
      setConnectPhase('');
    }
  }, [identity, onRefreshWallet, t, variant]);

  const resolveWalletLabel = useCallback((bindingKey) => {
    const wallet = [...visibleWallets, ...futureWalletBindings].find((entry) => entry.key === bindingKey);
    return wallet?.label || bindingKey;
  }, [futureWalletBindings, visibleWallets]);

  const handleOpenImportSeed = useCallback((bindingKey) => {
    setImportSeedError('');
    setImportSeedTarget({ key: bindingKey, label: resolveWalletLabel(bindingKey) });
  }, [resolveWalletLabel]);

  const handleImportManaged = useCallback(async ({ bindingKey, address, secret, secretFormat }) => {
    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setImportSeedError(t('wallet_connect_session_expired'));
        return;
      }
    }

    setConnectingKey(bindingKey);
    setConnectError(null);
    setConnectNotice(null);
    setConnectPhase(t('identity_import_seed_importing'));
    setImportSeedError('');
    setActiveWalletKey(bindingKey);

    const runImport = async (activeToken) => {
      await importManagedWalletBinding(activeToken, {
        binding_key: bindingKey,
        address,
        secret,
        secret_format: secretFormat,
      });
      setConnectPhase(t('wallet_connect_finishing'));
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      setConnectNotice({
        key: bindingKey,
        message: variant === 'vault' && identity
          ? t('identity_instrument_added', { alias: identityLabel(identity) })
          : t('identity_import_seed_success'),
      });
      setImportSeedTarget(null);
      setActiveWalletKey(bindingKey);
    };

    try {
      await runImport(token);
    } catch (exception) {
      const status = exception?.cause?.status ?? exception?.status;
      if (status === 401 || status === 403) {
        try {
          const freshToken = await refreshStorefrontVaultToken();
          await runImport(freshToken);
          return;
        } catch (retryException) {
          const message = retryException?.message || t('wallet_connect_session_expired');
          setImportSeedError(message);
          return;
        }
      }

      const message = exception?.message || t('identity_import_seed_error');
      setImportSeedError(message);
    } finally {
      setConnectingKey(null);
      setConnectPhase('');
    }
  }, [identity, onRefreshWallet, t, variant]);

  const handleRevokeInstrument = useCallback(async (walletBinding) => {
    const bindingId = walletBinding?.bindingId;
    const bindingKey = walletBinding?.key;
    if (!bindingId || !bindingKey) {
      setInstrumentActionError({ key: bindingKey, message: t('identity_instrument_revoke_missing') });
      return;
    }

    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setInstrumentActionError({ key: bindingKey, message: t('wallet_connect_session_expired') });
        return;
      }
    }

    setInstrumentActingKey(bindingKey);
    setInstrumentActionError(null);

    try {
      await revokeWalletBinding(token, bindingId);
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      setConnectNotice({
        key: bindingKey,
        message: t('identity_instrument_revoked_success', { network: walletBinding.label }),
      });
    } catch (exception) {
      const message = exception?.message || t('identity_instrument_revoke_error');
      setInstrumentActionError({ key: bindingKey, message });
    } finally {
      setInstrumentActingKey(null);
    }
  }, [onRefreshWallet, t]);

  const handleReplaceInstrument = useCallback(async (walletBinding) => {
    const bindingId = walletBinding?.bindingId;
    const bindingKey = walletBinding?.key;
    if (!bindingId || !bindingKey) {
      setInstrumentActionError({ key: bindingKey, message: t('identity_instrument_revoke_missing') });
      return;
    }

    let token = readStoredVaultToken();
    if (!token) {
      try {
        token = await refreshStorefrontVaultToken();
      } catch {
        setInstrumentActionError({ key: bindingKey, message: t('wallet_connect_session_expired') });
        return;
      }
    }

    setInstrumentActingKey(bindingKey);
    setInstrumentActionError(null);

    try {
      await revokeWalletBinding(token, bindingId);
      if (onRefreshWallet) {
        await onRefreshWallet();
      }
      handleOpenImportSeed(bindingKey);
    } catch (exception) {
      const message = exception?.message || t('identity_instrument_replace_error');
      setInstrumentActionError({ key: bindingKey, message });
    } finally {
      setInstrumentActingKey(null);
    }
  }, [handleOpenImportSeed, onRefreshWallet, t]);

  const handleRefreshWallet = useCallback(async () => {
    if (!onRefreshWallet) {
      return;
    }

    setRefreshingWallet(true);
    try {
      await onRefreshWallet();
    } finally {
      setRefreshingWallet(false);
    }
  }, [onRefreshWallet]);

  const handleRefreshBalances = useCallback(async () => {
    const refresh = onRefreshWalletAssets || onRefreshWallet;
    if (!refresh) {
      return;
    }

    setRefreshingWallet(true);
    try {
      await refresh();
    } finally {
      setRefreshingWallet(false);
    }
  }, [onRefreshWallet, onRefreshWalletAssets]);

  const hasWallet = Boolean(wallet && model);
  const showLoadingShell = isLoading || (isVaultOpen && !hasWallet && !error);
  const vaultShellOptions = useMemo(() => ({
    autoProvisionOnVault,
    managedWalletsEnabled,
  }), [autoProvisionOnVault, managedWalletsEnabled]);
  const showSafeProvisioningShell = isVaultVariant && model
    && shouldShowSafeProvisioningShell(model, variant, vaultShellOptions);
  const showSafeDashboard = isVaultVariant && model
    && shouldShowSafeDashboard(model, variant, vaultShellOptions);
  const showBindingsSection = hasWallet && !isVaultVariant && (visibleWallets.length > 0 || futureWalletBindings.length > 0);
  const showDashboardNetworks = Boolean(model)
    && !isVaultVariant
    && !showSafeDashboard
    && (visibleWallets.length > 0 || futureWalletBindings.length > 0);
  const suppressBindingAssets = isVaultVariant && summaryCoins.length > 0;
  const showBindingReceiveQr = isVaultVariant;
  const welcomeProvisionError = connectError?.key === connectingKey ? connectError.message : '';
  const defaultProvisionNetworkKey = resolveDefaultProvisionNetworkKey(managedNetworkKeys);
  const isProvisioningSafe = Boolean(connectingKey) && connectingKey === defaultProvisionNetworkKey;
  const welcomeConnectNotice = connectNotice?.key === defaultProvisionNetworkKey ? connectNotice : null;

  return (
    <>
      {hasWallet ? (
        <>
          {showSafeProvisioningShell ? (
            <SafeWelcomeCard
              connectNotice={welcomeConnectNotice}
              connectPhase={connectPhase}
              identity={identity}
              isProvisioning={isProvisioningSafe}
              managedWalletsEnabled={managedWalletsEnabled}
              onCreateSafe={() => (
                autoProvisionOnVault
                  ? handleActivateVault()
                  : handleCreateManaged(defaultProvisionNetworkKey)
              )}
              provisionError={welcomeProvisionError}
            />
          ) : showSafeDashboard ? (
            <IdentityAccountPanel
              autoProvisionOnVault={autoProvisionOnVault}
              connectError={connectError}
              connectNotice={connectNotice}
              connectPhase={connectPhase}
              connectingKey={connectingKey}
              futureWallets={futureWalletBindings}
              identity={identity}
              identityPaymentFlags={identityPaymentFlags}
              observationState={vaultBalanceObservationState(model?.wallets ?? [])}
              instrumentActionError={instrumentActionError}
              instrumentActingKey={instrumentActingKey}
              legacyConnectEnabled={legacyConnectEnabled}
              onConnect={handleConnect}
              onCreateManaged={handleCreateManaged}
              onImportManaged={handleOpenImportSeed}
              onRefreshWallet={handleRefreshWallet}
              onRefreshWalletAssets={handleRefreshBalances}
              onReplaceInstrument={handleReplaceInstrument}
              onRevokeInstrument={handleRevokeInstrument}
              polygonWallet={polygonWallet}
              refreshingWallet={refreshingWallet}
              summaryCoins={summaryCoins}
              visibleWallets={visibleWallets}
              walletBindings={walletBindings}
            />
          ) : !isVaultVariant ? (
            <WalletHero
              model={model}
              onRefreshWallet={handleRefreshWallet}
              refreshingWallet={refreshingWallet}
              t={t}
              variant={variant}
            />
          ) : null}

          {showBindingsSection ? (
            <div className="premium-wallet-body premium-wallet-body--bindings">
              <div className="premium-wallet-bindings">
              <div className="premium-wallet-bindings__header">
                <span>
                  {t('wallet_bindings_title')}
                  <GlossaryHint>{t('wallet_bindings_hint')}</GlossaryHint>
                </span>
                {connectPhase ? <small className="premium-wallet-bindings__phase">{connectPhase}</small> : null}
              </div>

              {visibleWallets.length ? (
                <div className="premium-wallet-bindings__list">
                  {visibleWallets.map((walletBinding) => (
                    <WalletBindingRow
                      connectError={connectError}
                      connectNotice={connectNotice}
                      connectingKey={connectingKey}
                      isActive={activeWalletKey === walletBinding.key}
                      key={walletBinding.key}
                      legacyConnectEnabled={legacyConnectEnabled}
                      onConnect={handleConnect}
                      onCreateManaged={handleCreateManaged}
                      onImportManaged={handleOpenImportSeed}
                      onSelect={setActiveWalletKey}
                      showReceiveQr={showBindingReceiveQr}
                      suppressAssets={suppressBindingAssets}
                      t={t}
                      walletBinding={walletBinding}
                    />
                  ))}
                </div>
              ) : null}

              {futureWalletBindings.length ? (
                <div className="premium-wallet-future">
                  <button
                    className="premium-wallet-future__toggle"
                    onClick={() => setShowFutureNetworks((current) => !current)}
                    type="button"
                  >
                    {showFutureNetworks ? t('wallet_future_networks_hide') : t('wallet_future_networks_show')}
                  </button>
                  {showFutureNetworks ? (
                    <div className="premium-wallet-bindings__list premium-wallet-bindings__list--future">
                      {futureWalletBindings.map((walletBinding) => (
                        <WalletBindingRow
                          connectError={connectError}
                          connectNotice={connectNotice}
                          connectingKey={connectingKey}
                          isActive={activeWalletKey === walletBinding.key}
                          key={walletBinding.key}
                          legacyConnectEnabled={legacyConnectEnabled}
                          onConnect={handleConnect}
                          onCreateManaged={handleCreateManaged}
                          onImportManaged={handleOpenImportSeed}
                          onSelect={setActiveWalletKey}
                          showReceiveQr={showBindingReceiveQr}
                          suppressAssets={suppressBindingAssets}
                          t={t}
                          walletBinding={walletBinding}
                        />
                      ))}
                    </div>
                  ) : null}
                </div>
              ) : null}
              </div>
            </div>
          ) : null}

          {showDashboardNetworks ? (
            <SafeNetworksSection>
              <div className="premium-wallet-bindings">
                <div className="premium-wallet-bindings__header">
                  <span>
                    {t('wallet_safe_networks_title')}
                    <GlossaryHint>{t('wallet_bindings_hint')}</GlossaryHint>
                  </span>
                  {connectPhase ? <small className="premium-wallet-bindings__phase">{connectPhase}</small> : null}
                </div>

                {visibleWallets.length ? (
                  <div className="premium-wallet-bindings__list">
                    {visibleWallets.map((walletBinding) => (
                      <WalletBindingRow
                        connectError={connectError}
                        connectNotice={connectNotice}
                        connectingKey={connectingKey}
                        isActive={activeWalletKey === walletBinding.key}
                        key={walletBinding.key}
                        legacyConnectEnabled={legacyConnectEnabled}
                        onConnect={handleConnect}
                        onCreateManaged={handleCreateManaged}
                        onImportManaged={handleOpenImportSeed}
                        onSelect={setActiveWalletKey}
                        showReceiveQr={showBindingReceiveQr}
                        suppressAssets={suppressBindingAssets}
                        suppressManagedProvisioning={
                          managedNetworkKeys?.has?.(walletBinding.key)
                          && walletBinding.bindingState === WALLET_BINDING_STATE.CONNECTED
                        }
                        t={t}
                        walletBinding={walletBinding}
                      />
                    ))}
                  </div>
                ) : null}

                {futureWalletBindings.length ? (
                  <div className="premium-wallet-future">
                    <button
                      className="premium-wallet-future__toggle"
                      onClick={() => setShowFutureNetworks((current) => !current)}
                      type="button"
                    >
                      {showFutureNetworks ? t('wallet_future_networks_hide') : t('wallet_future_networks_show')}
                    </button>
                    {showFutureNetworks ? (
                      <div className="premium-wallet-bindings__list premium-wallet-bindings__list--future">
                        {futureWalletBindings.map((walletBinding) => (
                          <WalletBindingRow
                            connectError={connectError}
                            connectNotice={connectNotice}
                            connectingKey={connectingKey}
                            isActive={activeWalletKey === walletBinding.key}
                            key={walletBinding.key}
                            legacyConnectEnabled={legacyConnectEnabled}
                            onConnect={handleConnect}
                            onCreateManaged={handleCreateManaged}
                            onImportManaged={handleOpenImportSeed}
                            onSelect={setActiveWalletKey}
                            showReceiveQr={showBindingReceiveQr}
                            suppressAssets={suppressBindingAssets}
                            t={t}
                            walletBinding={walletBinding}
                          />
                        ))}
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
            </SafeNetworksSection>
          ) : null}
        </>
      ) : showLoadingShell ? (
        <>
          <VaultWalletLoadingShell status={status} t={t} variant={variant} />
        </>
      ) : (
        <div className="premium-wallet-empty">
          <span>{t('wallet_shell_title')}</span>
          <strong>{error || status || t('wallet_shell_empty')}</strong>
          {error && isVaultOpen && onRefreshWallet ? (
            <button
              className="meanly-pill-button meanly-pill-button--compact"
              disabled={refreshingWallet}
              onClick={handleRefreshWallet}
              type="button"
            >
              {refreshingWallet ? t('wallet_shell_loading') : t('wallet_shell_retry')}
            </button>
          ) : (
            <p>{isVaultOpen && !error ? t('wallet_shell_loading') : t('wallet_shell_empty')}</p>
          )}
          {showOpenAction ? <Link href="/vault">{t('wallet_shell_open_vault')}</Link> : null}
        </div>
      )}

      {evmPickerRequest ? (
        <EvmWalletPicker
          onCancel={() => {
            dismissEvmPicker(new WalletConnectError('user_rejected', 'Wallet connection was cancelled.'));
          }}
          onSelect={(provider) => {
            evmPickerRequest.resolve(provider);
            setEvmPickerRequest(null);
          }}
          providers={evmPickerRequest.providers}
        />
      ) : null}
      <ImportSeedModal
        bindingKey={importSeedTarget?.key || ''}
        bindingLabel={importSeedTarget?.label || ''}
        error={importSeedError}
        isSubmitting={Boolean(importSeedTarget?.key) && connectingKey === importSeedTarget.key}
        onClose={() => {
          if (!connectingKey) {
            setImportSeedTarget(null);
            setImportSeedError('');
          }
        }}
        onImport={handleImportManaged}
        open={Boolean(importSeedTarget)}
        t={t}
      />
    </>
  );
}

export function PremiumWalletPanel() {
  const { t } = useLocale();
  const [wallet, setWallet] = useState(null);
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');

  const refreshWallet = useCallback(async () => {
    const token = readStoredVaultToken();
    if (!token) {
      setWallet(null);
      return;
    }

    const payload = await fetchWalletBundle(token);
    setWallet(payload);
    setStatus('');
    setError('');
  }, []);

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

      setStatus(t('wallet_shell_loading'));

      try {
        const payload = await fetchWalletBundle(token);
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
        setError(exception.message || t('wallet_shell_error'));
      }
    }

    loadWallet();

    return () => {
      cancelled = true;
    };
  }, [t]);

  return (
    <main className="page page--wallet">
      <section className="premium-wallet-shell premium-wallet-shell--compact">
        <VaultWalletContent
          error={error}
          onRefreshWallet={refreshWallet}
          status={status}
          wallet={wallet}
        />
      </section>
    </main>
  );
}
