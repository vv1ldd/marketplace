'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useLocale } from './LocaleProvider';
import { IdentityStateStage } from './IdentityStateStage';
import { MeanlyLoadingMark } from './MeanlyLoadingMark';
import { resolveSimpleL1ConnectHandoff } from '../lib/simple-l1-identity-center';

const CONNECT_PARAM_KEYS = [
  'return_to',
  'mode',
  'intent_type',
  'intent_title',
  'intent_description',
  'intent_cta',
  'intent_nonce',
  'intent_resource',
];

function pickConnectParams(params = {}) {
  const query = {};

  CONNECT_PARAM_KEYS.forEach((key) => {
    const value = params[key];
    if (value !== undefined && value !== null && value !== '') {
      query[key] = String(value);
    }
  });

  return query;
}

function friendlyConnectError(message, fallback) {
  const text = String(message || '').trim();
  const lower = text.toLowerCase();

  if (!text) {
    return fallback;
  }

  if (lower.includes('deadlock') || lower.includes('sqlstate')) {
    return fallback;
  }

  if (/^(vault:local-)?xchacha20:/i.test(text)) {
    return fallback;
  }

  return text;
}

export function IdentityConnectLauncher({ params = {} }) {
  const { t } = useLocale();
  const router = useRouter();
  const connectQuery = useMemo(() => pickConnectParams(params), [params]);
  const [phase, setPhase] = useState('loading');
  const [handoff, setHandoff] = useState(null);
  const [authorizePath, setAuthorizePath] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function boot() {
      setPhase('loading');
      setErrorMessage('');

      try {
        const payload = await resolveSimpleL1ConnectHandoff(connectQuery);

        if (cancelled) {
          return;
        }

        setAuthorizePath(payload.authorizePath);

        if (payload.showHandoff && payload.handoff) {
          setHandoff(payload.handoff);
          setPhase('handoff');
          return;
        }

        router.replace(payload.authorizePath);
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(friendlyConnectError(error?.message, t('sl1_connect_load_error')));
          setPhase('error');
        }
      }
    }

    boot();

    return () => {
      cancelled = true;
    };
  }, [connectQuery, router]);

  function continueToAuthorize() {
    if (authorizePath) {
      router.replace(authorizePath);
    }
  }

  return (
    <section className="identity-center-surface identity-center-surface--animated" aria-label={t('sl1_modal_title')}>
      <IdentityStateStage stageKey={phase}>
        {phase === 'handoff' && handoff ? (
          <div className="identity-center-surface__handoff">
            <div className="vault-modal-badge">Meanly One</div>
            <h1 className="identity-center-surface__title">{handoff.title || t('sl1_handoff_title')}</h1>
            <p className="identity-center-surface__body">{handoff.body || t('sl1_handoff_body')}</p>
            <button type="button" className="identity-center-surface__cta" onClick={continueToAuthorize}>
              {handoff.cta || t('sl1_handoff_cta')}
            </button>
          </div>
        ) : null}

        {phase === 'loading' ? (
          <div className="identity-center-surface__loading" aria-live="polite">
            <MeanlyLoadingMark label={t('sl1_connect_loading')} size="md" />
          </div>
        ) : null}

        {phase === 'error' ? (
          <div className="identity-center-surface__error" role="alert">
            <p>{errorMessage || t('sl1_connect_load_error')}</p>
            <Link href="/">{t('sl1_connect_continue')}</Link>
          </div>
        ) : null}
      </IdentityStateStage>
    </section>
  );
}
