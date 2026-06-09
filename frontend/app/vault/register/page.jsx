import { MeanlyConnectLink } from '../../../components/MeanlyConnectLink';
import { simpleL1ConnectUrl } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

export default function VaultRegisterPage() {
  const connectUrl = simpleL1ConnectUrl({
    mode: 'connect',
    intentType: 'meanly.vault.setup',
    intentTitle: 'Create Meanly identity',
    intentCta: 'Create identity',
    intentDescription: 'Create your Meanly identity first. Vault opens after identity exists.',
  });

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Identity</p>
        <h1>Create your Meanly identity.</h1>
        <p>Vault access starts after this identity exists.</p>
        <div className="product-card__actions">
          <MeanlyConnectLink
            href={connectUrl}
            statusLabel="Creating identity in Meanly One."
            unavailableLabel="Meanly One app is not available here. Create identity in browser."
            failureLabel="Identity cannot open the app here. Create identity in browser."
            onlineLabel="Open in browser"
          >
            Create identity
          </MeanlyConnectLink>
        </div>
      </section>
    </main>
  );
}
