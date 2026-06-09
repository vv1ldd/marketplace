'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { legalPageEntries } from '../lib/legal-pages';

export function MarketplaceFooter({ year }) {
  const pathname = usePathname();

  if (
    pathname === '/merchant'
    || pathname.startsWith('/merchant/')
    || pathname === '/partner'
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
      <nav aria-label="Юридические документы">
        {legalPageEntries().map(([key, item]) => (
          <Link key={key} href={item.href}>{item.title}</Link>
        ))}
      </nav>
    </footer>
  );
}
