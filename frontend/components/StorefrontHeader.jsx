'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { vaultHandoffUrl } from '../lib/storefront-api';
import { buildVaultConnectUrl, isVaultSurfacePath, navigateToVaultEntry } from '../lib/vault-entry';
import { useVaultHeaderLabel } from '../lib/vault-authority';
import { useLocale } from './LocaleProvider';

function VaultShieldIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M12 3 5 6v6c0 4.2 2.9 7.9 7 9 4.1-1.1 7-4.8 7-9V6l-7-3Z" fill="none" stroke="currentColor" strokeWidth="2.2" />
      <path d="M9.5 12.2 11.3 14l3.7-3.8" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" />
    </svg>
  );
}

export function StorefrontHeader({ authority = {} }) {
  const pathname = usePathname() || '/';
  const router = useRouter();
  const { t } = useLocale();
  const vaultReturnTo = vaultHandoffUrl({
    return_to: pathname.startsWith('/vault') || pathname.startsWith('/authorize') ? '/vault' : pathname,
  });
  const connectHref = buildVaultConnectUrl({
    returnTo: vaultReturnTo,
    mode: 'connect',
    intentTitle: t('header_connect_title'),
    intentCta: t('intent_cta'),
    intentDescription: t('header_connect_description'),
  });
  const vaultIntent = {
    returnTo: vaultReturnTo,
    intentTitle: t('header_connect_title'),
    intentCta: t('intent_cta'),
    intentDescription: t('header_connect_description'),
  };
  const isCatalogPath = pathname.startsWith('/catalog')
    || pathname.startsWith('/products')
    || pathname.startsWith('/store');
  const isVaultPath = isVaultSurfacePath(pathname);
  const vaultLabel = useVaultHeaderLabel(authority.vaultLabel || null);
  const showVaultUsername = authority.authenticated && Boolean(vaultLabel);
  const vaultLinkClassName = [
    'marketplace-header__link',
    'marketplace-header__link--accent',
    isVaultSurfacePath(pathname) ? 'is-active' : '',
    authority.authenticated && !showVaultUsername ? 'marketplace-header__link--icon' : '',
  ].filter(Boolean).join(' ');

  async function handleVaultClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();

    try {
      await navigateToVaultEntry(router, vaultIntent);
    } catch {
      window.location.assign(connectHref);
    }
  }

  return (
    <header className="meanly-storefront-header-shell">
      <nav className="meanly-standard-header marketplace-header--shop" aria-label={t('header_nav_label')}>
        <Link className="footer-logo" href="/" aria-label="Meanly">
          <span className="footer-logo__mark" aria-hidden="true" />
          MEANLY
        </Link>

        {!isVaultPath ? (
          <>
            <span className="marketplace-header__divider" aria-hidden="true">·</span>

            <Link
              className={`marketplace-header__link${isCatalogPath ? ' is-active' : ''}`}
              href="/catalog"
            >
              {t('nav_categories')}
            </Link>

            <span className="marketplace-header__divider" aria-hidden="true">·</span>
          </>
        ) : null}

        <div className="marketplace-header__actions">
          {authority.canAccessOps ? (
            <Link className="marketplace-header__link" href="/ops">Ops</Link>
          ) : null}
          {authority.canAccessPartner ? (
            <Link className="marketplace-header__link marketplace-header__link--accent" href="/merchant">{t('nav_merchant')}</Link>
          ) : null}
          <Link
            className={vaultLinkClassName}
            href={connectHref}
            onClick={handleVaultClick}
            aria-label={showVaultUsername ? vaultLabel : t('nav_vault')}
            title={showVaultUsername ? vaultLabel : t('nav_vault')}
          >
            <VaultShieldIcon />
            {showVaultUsername ? (
              <span className="marketplace-header__vault-label">{vaultLabel}</span>
            ) : authority.authenticated ? (
              <span className="sr-only">{t('nav_vault')}</span>
            ) : (
              t('header_connect_safe')
            )}
          </Link>
        </div>
      </nav>
    </header>
  );
}
