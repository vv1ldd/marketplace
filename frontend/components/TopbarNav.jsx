'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { GlossaryHint } from './GlossaryHint';
import { fetchVault, simpleL1ConnectUrl, vaultHandoffUrl } from '../lib/storefront-api';
import { clearVaultAuthorityState, readStoredVaultToken, writeCachedVault } from '../lib/vault-authority';
import { useLocale } from './LocaleProvider';

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

function sameOriginPath(url) {
  try {
    const parsed = new URL(url, window.location.origin);
    if (parsed.origin !== window.location.origin) {
      return null;
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return null;
  }
}

export function TopbarNav({ authority = {} }) {
  const pathname = usePathname() || '/';
  const router = useRouter();
  const { t } = useLocale();

  const vaultHref = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentTitle: t('intent_title'),
    intentCta: t('intent_cta'),
    intentDescription: t('intent_description'),
  });
  const canAccessPartner = Boolean(authority.canAccessPartner);
  const canAccessOps = Boolean(authority.canAccessOps);

  const openKnownVault = async () => {
    const token = readStoredVaultToken();
    if (!token) {
      return false;
    }

    try {
      const vault = await fetchVault(token);
      writeCachedVault(vault);
      router.push('/vault');
      return true;
    } catch (exception) {
      if ([401, 403].includes(exception.status)) {
        clearVaultAuthorityState();
      }
      return false;
    }
  };

  const handleVaultClick = async (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();
    if (await openKnownVault()) {
      return;
    }

    const returnTo = vaultHandoffUrl({
      return_to: `${window.location.pathname}${window.location.search}${window.location.hash}`,
    });
    const connectUrl = simpleL1ConnectUrl({
      returnTo,
      intentTitle: t('intent_title'),
      intentCta: t('intent_cta'),
      intentDescription: t('intent_description'),
    });

    try {
      const response = await fetch(connectUrl, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error('Vault handoff failed.');
      }

      const payload = await response.json();
      const localRedirect = sameOriginPath(payload.redirect_url);
      if (localRedirect) {
        router.push(localRedirect);
        return;
      }

      window.location.assign(payload.redirect_url || connectUrl);
    } catch {
      window.location.assign(connectUrl);
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
