import Link from 'next/link';
import { LoginStatusPanel } from '../../components/LoginStatusPanel';
import { simpleL1ConnectUrl } from '../../lib/storefront-api';

export const dynamic = 'force-dynamic';

export default function LoginPage() {
  const connectUrl = simpleL1ConnectUrl({
    mode: 'connect',
    intentType: 'meanly.vault.open',
    intentTitle: 'Open Meanly Vault',
    intentCta: 'Continue with Meanly',
    intentDescription: 'Approve sign-in in Meanly One and open your vault.',
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
        <LoginStatusPanel connectUrl={connectUrl} />
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
