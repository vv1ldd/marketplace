import Link from 'next/link';
import { LoginStatusPanel } from '../../components/LoginStatusPanel';
import { frontendUrl, merchantConnectUrl, simpleL1ConnectUrl } from '../../lib/storefront-api';

export const dynamic = 'force-dynamic';

function safeReturnTo(value) {
  const candidate = typeof value === 'string' ? value.trim() : '';
  if (!candidate || !candidate.startsWith('/') || candidate.startsWith('//')) {
    return '/vault';
  }

  return candidate;
}

export default async function LoginPage({ searchParams }) {
  const params = await searchParams;
  const returnTo = safeReturnTo(params?.return_to);
  const isMerchantReturn = returnTo === '/merchant' || returnTo.startsWith('/merchant/');
  const connectUrl = isMerchantReturn
    ? merchantConnectUrl(frontendUrl(returnTo))
    : simpleL1ConnectUrl({
        returnTo: frontendUrl(returnTo),
        mode: 'connect',
        intentType: 'meanly.vault.open',
        intentTitle: 'Open Meanly Vault',
        intentCta: 'Continue with Meanly',
        intentDescription: 'Approve sign-in in Meanly One and continue.',
      });

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Meanly Connect</p>
        <h1>Sign in with Meanly.</h1>
        <p>
          Use Meanly to open your vault, save products, and continue checkout.
          If the app does not open, you can continue in the browser.
        </p>
        <LoginStatusPanel connectUrl={connectUrl} returnTo={returnTo} />
        <p className="product-card__muted">
          New here? Start by browsing. Meanly is only needed when you save,
          buy, or open your vault.
        </p>
        <div className="product-card__actions">
          <Link href="/">Browse first</Link>
        </div>
      </section>
    </main>
  );
}
