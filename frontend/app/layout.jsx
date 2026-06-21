import './globals.css';
import { cookies, headers } from 'next/headers';
import { apiUrl, fetchStorefrontContext } from '../lib/storefront-api';
import { GlobalBackLink } from '../components/GlobalBackLink';
import { MarketplaceFooter } from '../components/MarketplaceFooter';
import { MeanlyAppShell } from '../components/MeanlyAppShell';
import { InteractionRecovery } from '../components/InteractionRecovery';
import { LocaleProvider } from '../components/LocaleProvider';
import { StorefrontHeader } from '../components/StorefrontHeader';
import { StorefrontThemeProvider } from '../components/StorefrontThemeProvider';
import { marketKeyForHost, resolveStorefrontHost } from '../lib/market-surface';

export const metadata = {
  title: 'Meanly',
  description: 'Product search, Vault, and Meanly Merchant Center.',
};

async function storefrontContext(forwardedHost) {
  try {
    return await fetchStorefrontContext({ forwardedHost });
  } catch (error) {
    return {
      market: {
        key: marketKeyForHost(forwardedHost),
        locale: marketKeyForHost(forwardedHost) === 'ru' ? 'ru' : 'en',
      },
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
      authenticated: false,
      canAccessOps: false,
      canAccessPartner: false,
      vaultLabel: null,
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
        authenticated: false,
        canAccessOps: false,
        canAccessPartner: false,
        vaultLabel: null,
      };
    }

    const authority = await response.json();

    return {
      authenticated: authority?.authenticated === true,
      canAccessOps: authority?.can_access_ops === true,
      canAccessPartner: authority?.can_access_partner === true,
      vaultLabel: typeof authority?.vault_label === 'string' ? authority.vault_label : null,
    };
  } catch {
    return {
      authenticated: false,
      canAccessOps: false,
      canAccessPartner: false,
      vaultLabel: null,
    };
  }
}

export default async function RootLayout({ children }) {
  const cookieStore = await cookies();
  const headerList = await headers();
  const storefrontHost = await resolveStorefrontHost(headerList);
  const [context, authority] = await Promise.all([
    storefrontContext(storefrontHost),
    storefrontAuthority(cookieStore),
  ]);
  const year = new Date().getFullYear();
  const marketKey = context?.market?.key || marketKeyForHost(storefrontHost);
  const locale = context?.market?.locale || (marketKey === 'ru' ? 'ru' : 'en');

  return (
    <html lang={locale} data-theme="retro" suppressHydrationWarning>
      <body>
        <LocaleProvider locale={locale}>
          <StorefrontThemeProvider>
            <InteractionRecovery />
            <StorefrontHeader authority={authority} />
            {context.service_error ? (
              <div className="banner">Marketplace data is temporarily unavailable: {context.service_error}</div>
            ) : null}
            <GlobalBackLink />
            <MeanlyAppShell authority={authority}>{children}</MeanlyAppShell>
            <MarketplaceFooter year={year} marketKey={marketKey} />
          </StorefrontThemeProvider>
        </LocaleProvider>
      </body>
    </html>
  );
}
