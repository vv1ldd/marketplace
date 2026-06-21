'use client';

import Link from 'next/link';
import { useLocale } from './LocaleProvider';

export function MeanlyHomeLink({ className = '' }) {
  const { t } = useLocale();

  return (
    <Link
      className={`identity-center-brand${className ? ` ${className}` : ''}`}
      href="/"
      aria-label={t('identity_center_home_link')}
    >
      <span className="identity-center-brand__mark" aria-hidden="true" />
      <span className="identity-center-brand__name">meanly</span>
    </Link>
  );
}
