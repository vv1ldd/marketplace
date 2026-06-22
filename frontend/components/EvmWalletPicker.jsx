'use client';

import { useLocale } from './LocaleProvider';

function ProviderIcon({ icon, label }) {
  if (icon) {
    return (
      <img
        alt=""
        className="evm-wallet-picker__icon"
        height={32}
        src={icon}
        width={32}
      />
    );
  }

  return (
    <span aria-hidden="true" className="evm-wallet-picker__icon evm-wallet-picker__icon--fallback">
      {label.slice(0, 1).toUpperCase()}
    </span>
  );
}

export function EvmWalletPicker({ providers = [], onSelect, onCancel }) {
  const { t } = useLocale();

  return (
    <div className="evm-wallet-picker">
      <button
        aria-label={t('wallet_evm_picker_cancel')}
        className="evm-wallet-picker__backdrop"
        onClick={onCancel}
        type="button"
      />
      <div className="evm-wallet-picker__panel" role="dialog" aria-modal="true" aria-labelledby="evm-wallet-picker-title">
        <header className="evm-wallet-picker__header">
          <h2 id="evm-wallet-picker-title">{t('wallet_evm_picker_title')}</h2>
          <p>{t('wallet_evm_picker_hint')}</p>
        </header>

        <ul className="evm-wallet-picker__list">
          {providers.map((entry) => (
            <li key={entry.providerId}>
              <button
                className="evm-wallet-picker__option"
                onClick={() => onSelect(entry)}
                type="button"
              >
                <ProviderIcon icon={entry.icon} label={entry.label} />
                <span className="evm-wallet-picker__option-copy">
                  <strong>{entry.label}</strong>
                  <small>{entry.providerId}</small>
                </span>
              </button>
            </li>
          ))}
        </ul>

        <button className="evm-wallet-picker__cancel" onClick={onCancel} type="button">
          {t('wallet_evm_picker_cancel')}
        </button>
      </div>
    </div>
  );
}
