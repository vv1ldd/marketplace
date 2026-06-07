'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

export function MarketplaceFooter({ year }) {
  const pathname = usePathname();

  if (
    pathname === '/partner'
    || pathname.startsWith('/partner/')
    || pathname === '/ops'
    || pathname.startsWith('/ops/')
  ) {
    return null;
  }

  return (
    <footer className="marketplace-footer marketplace-footer--shop">
      <Link href="/" className="footer-logo">
        <span className="footer-logo__mark" />
        MEANLY
      </Link>
      <span>© {year}</span>
    </footer>
  );
}
