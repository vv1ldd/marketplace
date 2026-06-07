'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';

const currentRouteKey = 'meanly:last-route';
const previousRouteKey = 'meanly:previous-route';

function backTarget(pathname) {
  if (pathname === '/' || pathname === '/catalog') {
    return null;
  }

  if (pathname === '/ops' || pathname.startsWith('/ops/')) {
    return null;
  }

  if (
    pathname.startsWith('/catalog/')
    || pathname.startsWith('/products/')
    || pathname.startsWith('/checkout')
    || pathname.startsWith('/products-search')
    || pathname.startsWith('/catalog-network')
  ) {
    return { href: '/', label: 'Back' };
  }

  return { href: '/', label: 'Back' };
}

function labelForPrevious(href, fallback) {
  if (!href) {
    return fallback;
  }

  if (href.startsWith('/products/')) {
    return 'Back';
  }

  if (href.startsWith('/catalog/groups/')) {
    return 'Back';
  }

  if (href === '/catalog' || href.startsWith('/catalog?') || href.startsWith('/catalog/')) {
    return 'Back';
  }

  return 'Back';
}

function internalReturnTo() {
  const returnTo = new URLSearchParams(window.location.search).get('return_to');

  if (!returnTo || !returnTo.startsWith('/') || returnTo.startsWith('//')) {
    return null;
  }

  return returnTo;
}

export function GlobalBackLink() {
  const pathname = usePathname() || '/';
  const router = useRouter();
  const [previousHref, setPreviousHref] = useState(null);
  const target = backTarget(pathname);

  useEffect(() => {
    const currentHref = `${window.location.pathname}${window.location.search}${window.location.hash}`;
    const explicitReturnTo = internalReturnTo();
    const lastHref = window.sessionStorage.getItem(currentRouteKey);

    if (explicitReturnTo && explicitReturnTo !== window.location.pathname) {
      window.sessionStorage.setItem(previousRouteKey, explicitReturnTo);
      setPreviousHref(explicitReturnTo);
    } else if (lastHref && lastHref !== currentHref) {
      window.sessionStorage.setItem(previousRouteKey, lastHref);
      setPreviousHref(lastHref);
    } else {
      setPreviousHref(window.sessionStorage.getItem(previousRouteKey));
    }

    window.sessionStorage.setItem(currentRouteKey, currentHref);
  }, [pathname]);

  if (!target) {
    return null;
  }

  const canUsePreviousRoute = pathname.startsWith('/vault')
    || pathname.startsWith('/meanly-ai')
    || pathname.startsWith('/checkout');
  const hasPreviousRoute = canUsePreviousRoute && previousHref && previousHref !== pathname;
  const href = hasPreviousRoute ? previousHref : target.href;
  const label = hasPreviousRoute ? labelForPrevious(previousHref, target.label) : target.label;
  const handleClick = (event) => {
    if (!hasPreviousRoute) {
      return;
    }

    event.preventDefault();
    router.push(previousHref);
  };

  return (
    <div className="global-back-shell">
      <Link className="back-link global-back-link" href={href} onClick={handleClick}>
        {label}
      </Link>
    </div>
  );
}
