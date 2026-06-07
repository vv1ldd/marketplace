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
    <section className="meanly-connect-panel">
      <div>
        <p className="eyebrow">{eyebrow}</p>
        <h2>{title}</h2>
        <p>{body}</p>
      </div>
      <div className="product-card__actions">
        <MeanlyConnectLink href={href}>Continue with Meanly</MeanlyConnectLink>
        {secondaryHref ? <Link href={secondaryHref}>{secondaryLabel}</Link> : null}
      </div>
    </section>
  );
}
