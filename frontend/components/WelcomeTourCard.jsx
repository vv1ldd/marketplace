'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { GlossaryHint } from './GlossaryHint';
import { useLocale } from './LocaleProvider';

const STORAGE_KEY = 'meanly:welcome-tour-dismissed';

const TOUR_STEPS = [
  {
    key: 'brand',
    label: 'maestrooo',
    body: 'A guided commerce surface: tell us the result, we route you to the right product.',
  },
  {
    key: 'meanly',
    label: 'Meanly',
    body: 'The marketplace layer that connects catalog, checkout, Vault, and future identity tools.',
  },
  {
    key: 'path',
    label: 'Your path',
    body: 'Search an outcome, open Vault when needed, and preview SL1, MCR, and MLP coins.',
  },
];

function dismissedByBrowser() {
  try {
    return window.localStorage?.getItem(STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

function persistDismissed() {
  try {
    window.localStorage?.setItem(STORAGE_KEY, '1');
  } catch {
    // Tour visibility is only a local preference.
  }
}

export function WelcomeTourCard() {
  const [isDismissed, setIsDismissed] = useState(true);
  const { t } = useLocale();

  useEffect(() => {
    setIsDismissed(dismissedByBrowser());
  }, []);

  function dismissTour() {
    persistDismissed();
    setIsDismissed(true);
  }

  const steps = TOUR_STEPS.map((step) => ({
    ...step,
    label: t(`tour_step_${step.key}_label`),
    body: t(`tour_step_${step.key}_body`),
  }));

  if (isDismissed) {
    return (
      <div className="welcome-tour-compact">
        <button className="welcome-tour-reopen" type="button" onClick={() => setIsDismissed(false)}>
          {t('btn_tour')}
        </button>
        <Link className="welcome-tour-reopen" href="/wallet">{t('cta_vault_wallet')}</Link>
      </div>
    );
  }

  return (
    <section className="welcome-tour-card" aria-label="Meanly welcome tour">
      <div className="welcome-tour-card__heading">
        <span>
          {t('tour_title')}
          <GlossaryHint>{t('tour_hint')}</GlossaryHint>
        </span>
        <strong>{t('tour_subtitle')}</strong>
        <p>
          {t('tour_desc')}
        </p>
      </div>

      <div className="welcome-tour-card__steps">
        {steps.map((step) => (
          <article key={step.key}>
            <span>{step.label}</span>
            <p>{step.body}</p>
          </article>
        ))}
      </div>

      <div className="welcome-tour-card__actions">
        <Link href="/wallet">
          {t('cta_open_wallet')}
          <GlossaryHint>{t('cta_open_wallet_hint')}</GlossaryHint>
        </Link>
        <button type="button" onClick={dismissTour}>{t('btn_got_it')}</button>
      </div>
    </section>
  );
}
