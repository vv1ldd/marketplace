'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';

function isActive(pathname, target) {
  if (target === '/') {
    return pathname === '/';
  }

  return pathname === target || pathname.startsWith(`${target}/`);
}

function navClass(pathname, target, baseClass = '') {
  return [
    baseClass,
    isActive(pathname, target) ? 'nav-active' : '',
  ].filter(Boolean).join(' ');
}

export function TopbarNav() {
  const nextPathname = usePathname() || '/';
  const router = useRouter();
  const [pathname, setPathname] = useState(nextPathname);
  const [currentHref, setCurrentHref] = useState(pathname);
  const vaultHref = pathname.startsWith('/vault')
    ? '/vault'
    : `/vault?return_to=${encodeURIComponent(currentHref)}`;

  const handleVaultClick = (event) => {
    if (pathname.startsWith('/vault')) {
      return;
    }

    event.preventDefault();
    const returnTo = `${window.location.pathname}${window.location.search}${window.location.hash}`;
    router.push(`/vault?return_to=${encodeURIComponent(returnTo)}`);
  };

  useEffect(() => {
    const sync = () => {
      setPathname(window.location.pathname || '/');
      setCurrentHref(`${window.location.pathname}${window.location.search}${window.location.hash}`);
    };

    sync();
    window.addEventListener('popstate', sync);
    window.addEventListener('meanly:navigation', sync);
    return () => {
      window.removeEventListener('popstate', sync);
      window.removeEventListener('meanly:navigation', sync);
    };
  }, [nextPathname]);

  if (pathname.startsWith('/ops')) {
    return null;
  }

  return (
    <nav aria-label="Primary">
      <Link className={navClass(pathname, '/')} href="/">Shop</Link>
      <Link className={navClass(pathname, '/vault', 'nav-primary')} href={vaultHref} onClick={handleVaultClick}>Vault</Link>
    </nav>
  );
}
