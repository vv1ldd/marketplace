'use client';

import { useCallback, useState } from 'react';
import { VaultCopyIcon } from './IdentityVaultIcons';

export function WalletSendIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d="m22 2-7 20-4-9-9-4Z"
        fill="none"
        stroke="currentColor"
        strokeLinejoin="round"
        strokeWidth="2"
      />
      <path d="M22 2 11 13" fill="none" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}

export function WalletReceiveIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d="M12 3v12m0 0 4-4m-4 4-4-4"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
      <path
        d="M5 21h14"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeWidth="2"
      />
    </svg>
  );
}

export function WalletMenuIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" strokeLinecap="round" strokeWidth="2" />
    </svg>
  );
}

export function WalletChevronLeftIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d="m15 6-6 6 6 6"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
    </svg>
  );
}

export function WalletChevronRightIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d="m9 6 6 6-6 6"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
    </svg>
  );
}

export function WalletNetworkIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <circle cx="6" cy="6" fill="none" r="2.5" stroke="currentColor" strokeWidth="2" />
      <circle cx="18" cy="6" fill="none" r="2.5" stroke="currentColor" strokeWidth="2" />
      <circle cx="12" cy="18" fill="none" r="2.5" stroke="currentColor" strokeWidth="2" />
      <path d="M8.2 7.8 10.5 15.5M15.8 7.8 13.5 15.5M8.5 6h7" fill="none" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}

export function WalletStatementIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path
        d="M7 4h10v16H7z"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
      />
      <path d="M9 8h6M9 12h6M9 16h4" fill="none" stroke="currentColor" strokeLinecap="round" strokeWidth="2" />
    </svg>
  );
}

export function WalletIdentityIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <circle cx="12" cy="8" fill="none" r="3.5" stroke="currentColor" strokeWidth="2" />
      <path
        d="M5 20c1.5-3.5 4.5-5 7-5s5.5 1.5 7 5"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeWidth="2"
      />
    </svg>
  );
}

export function WalletActivityIcon({ direction }) {
  const isIncoming = direction === 'incoming';

  return (
    <span className={`wallet-shell-activity-icon${isIncoming ? ' is-incoming' : ' is-outgoing'}`}>
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path
          d={isIncoming ? 'M12 16V8m0 0 4 4m-4-4 4-4' : 'M12 8v8m0 0 4-4m-4 4-4-4'}
          fill="none"
          stroke="currentColor"
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth="2"
        />
      </svg>
    </span>
  );
}

export function WalletShellHeader({
  accountName,
  address,
  fullAddress,
  addressKind = 'sl1',
  onMenu,
  menuLabel,
  copiedLabel = 'Copied',
}) {
  const [copied, setCopied] = useState(false);
  const copyTarget = fullAddress || address;

  const copyAddress = useCallback(async () => {
    if (!copyTarget || typeof navigator === 'undefined' || !navigator.clipboard) {
      return;
    }

    try {
      await navigator.clipboard.writeText(copyTarget);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1600);
    } catch {
      setCopied(false);
    }
  }, [copyTarget]);

  return (
    <header className="wallet-shell-header">
      <div className="wallet-shell-header__account">
        <strong>{accountName}</strong>
      </div>
      <button
        aria-label={menuLabel}
        className="wallet-shell-header__menu"
        onClick={onMenu}
        type="button"
      >
        <WalletMenuIcon />
      </button>
      {address ? (
        <button className="wallet-shell-header__address" onClick={copyAddress} type="button">
          <span
            className={`wallet-shell-header__address-dot wallet-shell-header__address-dot--${addressKind}`}
            aria-hidden="true"
          />
          <code>{address}</code>
          <VaultCopyIcon />
          {copied ? <small className="wallet-shell-header__copied">{copiedLabel}</small> : null}
        </button>
      ) : null}
    </header>
  );
}

export function WalletShellBalance({ amount, symbol, label }) {
  const display = symbol
    ? `${amount ?? '0'} ${symbol}`
    : (amount ? `$${amount}` : '$0.00');

  return (
    <div className="wallet-shell-balance">
      {label ? <span className="wallet-shell-balance__label">{label}</span> : null}
      <strong>{display}</strong>
    </div>
  );
}

export function WalletShellActions({ actions = [] }) {
  return (
    <div className="wallet-shell-actions" role="toolbar">
      {actions.map((action) => (
        <button
          className="wallet-shell-actions__item"
          disabled={action.disabled}
          key={action.key}
          onClick={action.onClick}
          type="button"
        >
          <span className="wallet-shell-actions__icon" aria-hidden="true">{action.icon}</span>
          <span>{action.label}</span>
        </button>
      ))}
    </div>
  );
}

export function WalletShellTabs({ tabs, active, onChange, ariaLabel }) {
  return (
    <nav aria-label={ariaLabel} className="wallet-shell-tabs" role="tablist">
      {tabs.map((tab) => (
        <button
          aria-selected={active === tab.key}
          className={active === tab.key ? 'is-active' : ''}
          key={tab.key}
          onClick={() => onChange(tab.key)}
          role="tab"
          type="button"
        >
          {tab.label}
        </button>
      ))}
    </nav>
  );
}

export function WalletSettingsScreen({ title, onBack, backLabel, children }) {
  return (
    <div className="wallet-shell-settings-screen">
      <header className="wallet-shell-settings-screen__header">
        <button aria-label={backLabel} className="wallet-shell-settings-screen__back" onClick={onBack} type="button">
          <WalletChevronLeftIcon />
        </button>
        <strong>{title}</strong>
      </header>
      <div className="wallet-shell-settings-screen__body">{children}</div>
    </div>
  );
}

export function WalletSettingsMenu({ sections, onSelect, onBack, backLabel, title }) {
  return (
    <WalletSettingsScreen backLabel={backLabel} onBack={onBack} title={title}>
      {sections.map((section) => (
        <section className="wallet-shell-menu-section" key={section.key}>
          {section.label ? <h3>{section.label}</h3> : null}
          <div className="wallet-shell-menu-list" role="list">
            {section.items.map((item) => (
              <button
                className="wallet-shell-menu-item"
                key={item.key}
                onClick={() => onSelect(item.key)}
                role="listitem"
                type="button"
              >
                <span className="wallet-shell-menu-item__icon" aria-hidden="true">{item.icon}</span>
                <span className="wallet-shell-menu-item__label">{item.label}</span>
                <WalletChevronRightIcon />
              </button>
            ))}
          </div>
        </section>
      ))}
    </WalletSettingsScreen>
  );
}

export function WalletTokenRow({ symbol, amount, fiatAmount }) {
  return (
    <article className="wallet-shell-token-row">
      <span className="wallet-shell-token-row__icon">{symbol.slice(0, 1)}</span>
      <div className="wallet-shell-token-row__copy">
        <strong>{symbol}</strong>
      </div>
      <div className="wallet-shell-token-row__amounts">
        <strong>{amount}</strong>
        {fiatAmount ? <small>{fiatAmount}</small> : null}
      </div>
    </article>
  );
}

export function WalletActivityRow({
  title,
  status,
  statusTone = 'default',
  amount,
  fiatAmount,
  icon,
  onSelect,
}) {
  return (
    <button className="wallet-shell-activity-row" onClick={onSelect} type="button">
      {icon}
      <div className="wallet-shell-activity-row__copy">
        <strong>{title}</strong>
        <span className={`wallet-shell-activity-row__status wallet-shell-activity-row__status--${statusTone}`}>
          {status}
        </span>
      </div>
      <div className="wallet-shell-activity-row__amounts">
        <strong>{amount}</strong>
        {fiatAmount ? <small>{fiatAmount}</small> : null}
      </div>
    </button>
  );
}

export function WalletActivityGroup({ dateLabel, children }) {
  return (
    <section className="wallet-shell-activity-group">
      <h3>{dateLabel}</h3>
      <div className="wallet-shell-activity-list">{children}</div>
    </section>
  );
}
