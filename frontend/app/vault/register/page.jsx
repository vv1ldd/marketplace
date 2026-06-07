import { MeanlyConnectLink } from '../../../components/MeanlyConnectLink';
import { simpleL1ConnectUrl } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

export default function VaultRegisterPage() {
  const connectUrl = simpleL1ConnectUrl({
    mode: 'connect',
    intentType: 'meanly.vault.setup',
    intentTitle: 'Open Meanly Vault',
    intentCta: 'Open Vault',
    intentDescription: 'Use your Meanly One identity or create it, then open Vault access.',
  });

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Vault</p>
        <h1>Open your Meanly Vault.</h1>
        <p>Sign in to see purchases, receipts, safe codes, and saved products.</p>
        <div className="product-card__actions">
          <MeanlyConnectLink
            href={connectUrl}
            statusLabel="Opening Vault in Meanly One."
            unavailableLabel="Meanly One app is not available here. Open Vault in browser."
            failureLabel="Vault cannot open the app here. Open Vault in browser."
            onlineLabel="Open in browser"
          >
            Open Vault
          </MeanlyConnectLink>
        </div>
      </section>
    </main>
  );
}
