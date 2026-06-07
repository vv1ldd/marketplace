import Link from 'next/link';
import { MeanlyConnectPanel } from '../../components/MeanlyConnectPanel';
import { simpleL1ConnectUrl } from '../../lib/storefront-api';

export const dynamic = 'force-dynamic';

export default function RegisterPage() {
  const connectUrl = simpleL1ConnectUrl({
    mode: 'connect',
    intentType: 'meanly.identity.continue',
    intentTitle: 'Continue with Meanly identity',
    intentCta: 'Continue with Meanly',
    intentDescription: 'Use Meanly to save products, buy, and open your vault.',
  });

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Meanly account</p>
        <h1>Set up your Meanly access.</h1>
        <p>
          Create or use your Meanly sign-in so purchases, receipts, and saved
          products can stay together.
        </p>
        <MeanlyConnectPanel
          href={connectUrl}
          title="Continue with Meanly."
          body="Use Meanly when you are ready to save, buy, or open your vault."
          secondaryHref="/"
          secondaryLabel="Browse first"
        />
        <div className="product-card__actions">
          <Link href="/vault">Open Vault</Link>
        </div>
      </section>
    </main>
  );
}
