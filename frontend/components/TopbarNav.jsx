'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { GlossaryHint } from './GlossaryHint';
import { fetchVault, simpleL1ConnectUrl, vaultHandoffUrl } from '../lib/storefront-api';
import { clearVaultAuthorityState, readStoredVaultToken, writeCachedVault } from '../lib/vault-authority';

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
  const vaultHref = simpleL1ConnectUrl({
    returnTo: vaultHandoffUrl(),
    intentTitle: 'Open Meanly Vault',
    intentCta: 'Open Vault',
    intentDescription: 'Open your Vault.',
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
      intentTitle: 'Open Meanly Vault',
      intentCta: 'Open Vault',
      intentDescription: 'Open your Vault.',
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
      <Link className={navClass(pathname, '/')} href="/">Shop</Link>
      <Link className={navClass(pathname, '/catalog')} href="/catalog">
        Categories
        <GlossaryHint>Grouped by what you want to buy or do.</GlossaryHint>
      </Link>
      <Link className={navClass(pathname, '/vault', 'nav-primary')} href={vaultHref} onClick={handleVaultClick}>
        Vault
        <GlossaryHint>Your protected place for saved items, orders, and account access.</GlossaryHint>
      </Link>
      {canAccessPartner ? <Link className={navClass(pathname, '/merchant')} href="/merchant">Merchant</Link> : null}
      {canAccessOps ? <Link className={navClass(pathname, '/ops')} href="/ops">Ops</Link> : null}
    </nav>
  );
}
