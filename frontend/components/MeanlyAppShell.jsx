'use client';

import { usePathname } from 'next/navigation';
import { isVaultSurfacePath } from '../lib/vault-entry';

function surfaceFor(pathname) {
  if (pathname.startsWith('/ops')) return 'ops';
  if (pathname.startsWith('/merchant') || pathname.startsWith('/partner')) return 'merchant';
  if (isVaultSurfacePath(pathname)) return 'vault';
  if (pathname.startsWith('/business') || pathname.startsWith('/legal-entities')) return 'merchant';
  return 'shop';
}

export function MeanlyAppShell({ authority = {}, children }) {
  const pathname = usePathname() || '/';
  const surface = surfaceFor(pathname);

  return (
    <div className={`meanly-app-shell meanly-app-shell--${surface}`}>
      {children}
    </div>
  );
}
