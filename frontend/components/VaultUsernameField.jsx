'use client';

import { useEffect, useState } from 'react';
import { useLocale } from './LocaleProvider';
import { checkVaultUsername, normalizeVaultUsername } from '../lib/vault-username';

export function VaultUsernameField({
  value,
  onChange,
  onValidityChange,
  disabled = false,
}) {
  const { t } = useLocale();
  const [status, setStatus] = useState('idle');
  const [feedback, setFeedback] = useState('');
  const [normalized, setNormalized] = useState(null);

  useEffect(() => {
    onValidityChange?.({ status, normalized });
  }, [normalized, onValidityChange, status]);

  useEffect(() => {
    const raw = String(value || '').trim();

    if (!raw) {
      setStatus('idle');
      setFeedback('');
      setNormalized(null);
      return undefined;
    }

    const candidate = normalizeVaultUsername(raw);
    if (!candidate) {
      setStatus('invalid');
      setFeedback(t('vault_authorize_nickname_invalid'));
      setNormalized(null);
      return undefined;
    }

    setNormalized(candidate);
    setStatus('checking');
    setFeedback(t('vault_authorize_nickname_checking'));

    let cancelled = false;
    const timer = window.setTimeout(async () => {
      try {
        const result = await checkVaultUsername(candidate);
        if (cancelled) {
          return;
        }

        if (result.available) {
          setStatus('available');
          setFeedback(t('vault_authorize_nickname_available'));
          return;
        }

        setStatus('taken');
        setFeedback(result.message || t('vault_authorize_nickname_taken'));
      } catch (exception) {
        if (cancelled) {
          return;
        }

        setStatus('invalid');
        setFeedback(exception.message || t('vault_authorize_nickname_invalid'));
      }
    }, 350);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [t, value]);

  const inputClass = [
    'identity-center-input',
    'identity-center-input--alias',
    status === 'available' ? 'is-valid' : '',
    status === 'taken' || status === 'invalid' ? 'is-invalid' : '',
  ].filter(Boolean).join(' ');

  const feedbackClass = [
    'identity-center-field__feedback',
    status === 'available' ? 'is-valid' : '',
    status === 'taken' || status === 'invalid' ? 'is-invalid' : '',
  ].filter(Boolean).join(' ');

  return (
    <div className="identity-center-field identity-center-field--alias">
      <label className="identity-center-field__label" htmlFor="vault-username">
        {t('vault_authorize_nickname_label')}
      </label>
      <div className={`identity-center-alias-shell${status === 'available' ? ' is-valid' : ''}${status === 'taken' || status === 'invalid' ? ' is-invalid' : ''}`}>
        <span className="identity-center-alias-shell__prefix" aria-hidden="true">
          @
        </span>
        <input
          id="vault-username"
          className={inputClass}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={t('vault_authorize_nickname_placeholder')}
          name="vault-display-name"
          autoComplete="off"
          autoCorrect="off"
          autoCapitalize="none"
          spellCheck={false}
          disabled={disabled}
          inputMode="text"
          data-1p-ignore="true"
          data-lpignore="true"
        />
      </div>
      <p className="identity-center-field__hint">{t('vault_authorize_nickname_hint')}</p>
      {feedback ? <p className={feedbackClass}>{feedback}</p> : null}
    </div>
  );
}
