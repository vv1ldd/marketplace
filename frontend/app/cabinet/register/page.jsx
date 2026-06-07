import Link from 'next/link';
import { MeanlyConnectPanel } from '../../../components/MeanlyConnectPanel';
import { simpleL1ConnectUrl } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

export default function CabinetRegisterPage() {
  const connectUrl = simpleL1ConnectUrl({
    mode: 'connect',
    intentType: 'meanly.cabinet.setup',
    intentTitle: 'Continue to Meanly cabinet',
    intentCta: 'Continue with Meanly',
    intentDescription: 'Use your Meanly sign-in to open cabinet setup.',
  });

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Cabinet setup</p>
        <h1>Set up Meanly cabinet</h1>
        <p>
          Your cabinet keeps account details, purchases, vault access, and saved
          products together.
        </p>
        <MeanlyConnectPanel
          href={connectUrl}
          title="Connect to set up Cabinet."
          body="Sign in with Meanly to set up your account and vault access."
          secondaryHref="/vault"
          secondaryLabel="Open Vault"
        />
        <div className="product-card__actions">
          <Link href="/">Browse first</Link>
        </div>
      </section>
    </main>
  );
}
