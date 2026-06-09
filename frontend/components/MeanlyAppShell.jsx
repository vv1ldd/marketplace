'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useLocale } from './LocaleProvider';

const BASE_SURFACES = [
  { key: 'shop', label: 'Shop', href: '/', match: (pathname) => pathname === '/' },
  { key: 'categories', label: 'Categories', href: '/catalog', match: (pathname) => pathname.startsWith('/catalog') || pathname.startsWith('/products') || pathname.startsWith('/store') },
  { key: 'vault', label: 'Vault', href: '/vault', match: (pathname) => pathname.startsWith('/vault') },
];

function surfaceFor(pathname) {
  if (pathname.startsWith('/ops')) return 'ops';
  if (pathname.startsWith('/merchant') || pathname.startsWith('/partner')) return 'merchant';
  if (pathname.startsWith('/vault')) return 'vault';
  if (pathname.startsWith('/business') || pathname.startsWith('/legal-entities')) return 'merchant';
  return 'shop';
}

function navItems({ canAccessOps = false } = {}) {
  const items = [...BASE_SURFACES];
  items.push({ key: 'merchant', label: 'Merchant', href: '/merchant', match: (value) => value.startsWith('/merchant') || value.startsWith('/partner') || value.startsWith('/business') || value.startsWith('/legal-entities') });
  if (canAccessOps) {
    items.push({ key: 'ops', label: 'Ops', href: '/ops', match: (value) => value.startsWith('/ops') });
  }
  return items;
}

export function MeanlyAppShell({ authority = {}, children }) {
  const pathname = usePathname() || '/';
  const surface = surfaceFor(pathname);
  const hasMerchantAccess = Boolean(authority.canAccessPartner);
  const hasOpsAccess = Boolean(authority.canAccessOps);
  const { t } = useLocale();

  return (
    <div className={`meanly-app-shell meanly-app-shell--${surface}`}>
      {children}
      <nav aria-label="Mobile primary" className="meanly-mobile-nav">
        {navItems({ canAccessOps: hasOpsAccess }).filter((item) => item.key !== 'merchant' || hasMerchantAccess).map((item) => (
          <Link className={item.match(pathname) ? 'is-active' : ''} href={item.href} key={item.key}>
            <span>{t(`nav_${item.key}`)}</span>
          </Link>
        ))}
      </nav>
    </div>
  );
}
