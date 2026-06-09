import './globals.css';
import { cookies } from 'next/headers';
import { apiUrl, fetchStorefrontContext } from '../lib/storefront-api';
import { GlobalBackLink } from '../components/GlobalBackLink';
import { MarketplaceFooter } from '../components/MarketplaceFooter';
import { MeanlyAppShell } from '../components/MeanlyAppShell';
import { TopbarNav } from '../components/TopbarNav';
import { LocaleProvider } from '../components/LocaleProvider';

export const metadata = {
  title: 'Meanly',
  description: 'Product search, Vault, and Meanly Merchant Center.',
};

async function storefrontContext() {
  try {
    return await fetchStorefrontContext();
  } catch (error) {
    return {
      market: { key: process.env.NEXT_PUBLIC_MARKETPLACE_REGION || 'global', locale: 'en' },
      service_error: error.message,
    };
  }
}

function cookieHeader(cookieStore) {
  return cookieStore
    .getAll()
    .map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`)
    .join('; ');
}

async function storefrontAuthority(cookieStore) {
  const cookie = cookieHeader(cookieStore);
  if (!cookie) {
    return {
      canAccessOps: false,
      canAccessPartner: false,
    };
  }

  try {
    const response = await fetch(`${apiUrl}/api/storefront/v1/identity/navigation-authority`, {
      headers: {
        Accept: 'application/json',
        Cookie: cookie,
      },
      cache: 'no-store',
    });

    if (!response.ok) {
      return {
        canAccessOps: false,
        canAccessPartner: false,
      };
    }

    const authority = await response.json();

    return {
      canAccessOps: authority?.can_access_ops === true,
      canAccessPartner: authority?.can_access_partner === true,
    };
  } catch {
    return {
      canAccessOps: false,
      canAccessPartner: false,
    };
  }
}

export default async function RootLayout({ children }) {
  const cookieStore = await cookies();
  const [context, authority] = await Promise.all([
    storefrontContext(),
    storefrontAuthority(cookieStore),
  ]);
  const year = new Date().getFullYear();
  const locale = context?.market?.locale || 'en';

  return (
    <html lang={locale}>
      <body>
        <LocaleProvider locale={locale}>
          <header className="topbar">
            <TopbarNav authority={authority} />
          </header>
          {context.service_error ? (
            <div className="banner">Marketplace data is temporarily unavailable: {context.service_error}</div>
          ) : null}
          <GlobalBackLink />
          <MeanlyAppShell authority={authority}>{children}</MeanlyAppShell>
          <MarketplaceFooter year={year} />
        </LocaleProvider>
      </body>
    </html>
  );
}
