'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { GlossaryHint } from './GlossaryHint';
import { useLocale } from './LocaleProvider';
import { buildVaultConnectUrl, navigateToVaultEntry } from '../lib/vault-entry';

function isActive(pathname, target) {
  if (target === '/') {
    return pathname === '/';
  }

  if (target === '/merchant') {
    return pathname.startsWith('/merchant')
      || pathname.startsWith('/partner')
      || pathname.startsWith('/business')
      || pathname.startsWith('/legal-entities');
  }

  return pathname === target || pathname.startsWith(`${target}/`);
}

function navClass(pathname, target, baseClass = '') {
  return [
    baseClass,
    isActive(pathname, target) ? 'nav-active' : '',
  ].filter(Boolean).join(' ');
}

export function TopbarNav({ authority = {} }) {
  const pathname = usePathname() || '/';
  const router = useRouter();
  const { t } = useLocale();

  const vaultHref = buildVaultConnectUrl({
    intentTitle: t('intent_title'),
    intentCta: t('intent_cta'),
    intentDescription: t('intent_description'),
  });
  const canAccessPartner = Boolean(authority.canAccessPartner);
  const canAccessOps = Boolean(authority.canAccessOps);

  const handleVaultClick = async (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();

    try {
      await navigateToVaultEntry(router, {
        returnTo: `${window.location.pathname}${window.location.search}${window.location.hash}`,
        intentTitle: t('intent_title'),
        intentCta: t('intent_cta'),
        intentDescription: t('intent_description'),
      });
    } catch {
      window.location.assign(vaultHref);
    }
  };

  return (
    <nav aria-label="Primary">
      <Link className={navClass(pathname, '/')} href="/">{t('nav_shop')}</Link>
      <Link className={navClass(pathname, '/catalog')} href="/catalog">
        {t('nav_categories')}
        <GlossaryHint>{t('categories_hint')}</GlossaryHint>
      </Link>
      <Link className={navClass(pathname, '/vault', 'nav-primary')} href={vaultHref} onClick={handleVaultClick}>
        {t('nav_vault')}
        <GlossaryHint>{t('vault_hint')}</GlossaryHint>
      </Link>
      {canAccessPartner ? <Link className={navClass(pathname, '/merchant')} href="/merchant">{t('nav_merchant')}</Link> : null}
      {canAccessOps ? <Link className={navClass(pathname, '/ops')} href="/ops">{t('nav_ops')}</Link> : null}
    </nav>
  );
}
