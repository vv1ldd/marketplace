'use client';

import { deriveManagedWalletFromMnemonic, supportsManagedSeedImport } from '../lib/managed-wallet-import';
import { useEffect, useMemo, useState } from 'react';

export function ImportSeedModal({
  bindingKey,
  bindingLabel,
  open,
  onClose,
  onImport,
  isSubmitting = false,
  error = '',
  t,
}) {
  const [mnemonic, setMnemonic] = useState('');
  const [localError, setLocalError] = useState('');
  const [previewAddress, setPreviewAddress] = useState('');

  useEffect(() => {
    if (!open) {
      setMnemonic('');
      setLocalError('');
      setPreviewAddress('');
    }
  }, [open, bindingKey]);

  const canImport = useMemo(
    () => Boolean(bindingKey) && supportsManagedSeedImport(bindingKey),
    [bindingKey],
  );

  const networkLabel = bindingLabel || bindingKey || 'network';

  const handlePreview = async () => {
    setLocalError('');
    setPreviewAddress('');

    if (!canImport) {
      setLocalError(t('identity_import_seed_unsupported'));
      return;
    }

    try {
      const material = await deriveManagedWalletFromMnemonic(bindingKey, mnemonic);
      setPreviewAddress(material.address);
    } catch (exception) {
      setLocalError(exception?.message || t('identity_import_seed_error'));
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLocalError('');

    if (!canImport) {
      setLocalError(t('identity_import_seed_unsupported'));
      return;
    }

    try {
      const material = await deriveManagedWalletFromMnemonic(bindingKey, mnemonic);
      await onImport({
        bindingKey,
        ...material,
      });
    } catch (exception) {
      setLocalError(exception?.message || t('identity_import_seed_error'));
    }
  };

  if (!open) {
    return null;
  }

  const displayError = error || localError;
  const bodyKey = bindingKey === 'ton' ? 'identity_import_seed_body_ton' : 'identity_import_seed_body';

  return (
    <div className="import-seed-modal" role="presentation">
      <button
        aria-label={t('identity_import_seed_close')}
        className="import-seed-modal__backdrop"
        onClick={onClose}
        type="button"
      />
      <form className="import-seed-modal__panel" onSubmit={handleSubmit}>
        <header className="import-seed-modal__header">
          <span className="import-seed-modal__kicker">{t('identity_import_seed_kicker')}</span>
          <strong>{t('identity_import_seed_title', { network: networkLabel })}</strong>
          <p>{t(bodyKey, { network: networkLabel })}</p>
        </header>

        <ol className="import-seed-modal__steps">
          <li>{t('identity_import_seed_step_derive')}</li>
          <li>{t('identity_import_seed_step_attach', { network: networkLabel })}</li>
          <li>{t('identity_import_seed_step_recovery')}</li>
        </ol>

        <aside className="import-seed-modal__callout">
          <strong>{t('identity_import_seed_no_export_title')}</strong>
          <p>{t('identity_import_seed_no_export')}</p>
        </aside>

        <label className="import-seed-modal__field">
          <span>{t('identity_import_seed_label')}</span>
          <textarea
            autoComplete="off"
            disabled={isSubmitting}
            onChange={(event) => setMnemonic(event.target.value)}
            placeholder={t('identity_import_seed_placeholder')}
            rows={4}
            spellCheck={false}
            value={mnemonic}
          />
        </label>

        {previewAddress ? (
          <p className="import-seed-modal__preview">
            <span>{t('identity_import_seed_preview')}</span>
            <code>{previewAddress}</code>
          </p>
        ) : null}

        {displayError ? <p className="identity-send-error">{displayError}</p> : null}

        <div className="import-seed-modal__actions">
          <button
            className="identity-account-secondary"
            disabled={isSubmitting || !mnemonic.trim()}
            onClick={handlePreview}
            type="button"
          >
            {t('identity_import_seed_preview_cta')}
          </button>
          <button className="identity-account-primary" disabled={isSubmitting || !mnemonic.trim()} type="submit">
            {isSubmitting ? t('identity_import_seed_importing') : t('identity_import_seed_import_cta')}
          </button>
        </div>

        <p className="import-seed-modal__hint">{t('identity_import_seed_hint')}</p>
      </form>
    </div>
  );
}
