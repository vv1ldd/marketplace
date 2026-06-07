import './globals.css';
import { fetchStorefrontContext } from '../lib/storefront-api';
import { GlobalBackLink } from '../components/GlobalBackLink';
import { MarketplaceFooter } from '../components/MarketplaceFooter';
import { MeanlyAppShell } from '../components/MeanlyAppShell';
import { TopbarNav } from '../components/TopbarNav';

export const metadata = {
  title: 'Meanly',
  description: 'Product search, Vault, and Meanly Merchant Center.',
};

async function storefrontContext() {
  try {
    return await fetchStorefrontContext();
  } catch (error) {
    return {
      market: { key: process.env.NEXT_PUBLIC_MARKETPLACE_REGION || 'global' },
      service_error: error.message,
    };
  }
}

export default async function RootLayout({ children }) {
  const context = await storefrontContext();
  const year = new Date().getFullYear();

  return (
    <html lang="en">
      <body>
        <header className="topbar">
          <TopbarNav />
        </header>
        {context.service_error ? (
          <div className="banner">Marketplace data is temporarily unavailable: {context.service_error}</div>
        ) : null}
        <GlobalBackLink />
        <MeanlyAppShell>{children}</MeanlyAppShell>
        <MarketplaceFooter year={year} />
      </body>
    </html>
  );
}
