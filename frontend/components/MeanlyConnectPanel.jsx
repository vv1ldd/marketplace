'use client';

import Link from 'next/link';
import { MeanlyConnectLink } from './MeanlyConnectLink';

export function MeanlyConnectPanel({
  href,
  eyebrow = 'Meanly Connect',
  title = 'Continue with Meanly',
  body = 'Browse freely. Sign in when you buy, save, or open your vault.',
  secondaryHref = '/vault',
  secondaryLabel = 'Open Vault',
}) {
  return (
    <section className="connect-card meanly-connect-panel">
      <span className="connect-card__mark" aria-hidden="true" />
      <p className="eyebrow">{eyebrow}</p>
      <h2>{title}</h2>
      <p>{body}</p>
      <div className="connect-card__actions">
        <MeanlyConnectLink href={href} className="connect-cta connect-cta--primary">
          Continue with Meanly
        </MeanlyConnectLink>
        {secondaryHref ? (
          <Link className="connect-cta connect-cta--ghost" href={secondaryHref}>
            {secondaryLabel}
          </Link>
        ) : null}
      </div>
    </section>
  );
}
