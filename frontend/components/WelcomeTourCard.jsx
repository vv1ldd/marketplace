'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { GlossaryHint } from './GlossaryHint';

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

  useEffect(() => {
    setIsDismissed(dismissedByBrowser());
  }, []);

  function dismissTour() {
    persistDismissed();
    setIsDismissed(true);
  }

  if (isDismissed) {
    return (
      <div className="welcome-tour-compact">
        <button className="welcome-tour-reopen" type="button" onClick={() => setIsDismissed(false)}>
          Tour
        </button>
        <Link className="welcome-tour-reopen" href="/wallet">Vault Wallet</Link>
      </div>
    );
  }

  return (
    <section className="welcome-tour-card" aria-label="Meanly welcome tour">
      <div className="welcome-tour-card__heading">
        <span>
          Welcome to maestrooo
          <GlossaryHint>maestrooo is the guided front door for finding the right product or outcome.</GlossaryHint>
        </span>
        <strong>We start from your intent, then connect the right Meanly surface.</strong>
        <p>
          Meanly is a commerce and identity layer for digital outcomes: catalog search, protected Vault, Vault Wallet coins, and merchant tools.
        </p>
      </div>

      <div className="welcome-tour-card__steps">
        {TOUR_STEPS.map((step) => (
          <article key={step.key}>
            <span>{step.label}</span>
            <p>{step.body}</p>
          </article>
        ))}
      </div>

      <div className="welcome-tour-card__actions">
        <Link href="/wallet">
          Open Vault Wallet
          <GlossaryHint>Preview SL1, MCR, and MLP coins bound to your Vault identity.</GlossaryHint>
        </Link>
        <button type="button" onClick={dismissTour}>Got it</button>
      </div>
    </section>
  );
}
