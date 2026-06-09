import { Suspense } from 'react';
import { WalletAuthorizePanel } from '../../components/WalletAuthorizePanel';

export const dynamic = 'force-dynamic';

export const metadata = {
  title: 'Create Identity | Meanly',
  description: 'Create your Meanly identity before opening Vault.',
};

export default function AuthorizePage() {
  return (
    <Suspense fallback={(
      <main className="page page--vault-authorize">
        <section className="vault-authorize-panel vault-authorize-panel--standalone">
          <div className="vault-authorize-primary-action">
            <button type="button" disabled>
              <span>Create identity</span>
            </button>
          </div>
          <div className="vault-authorize-message-slot" aria-hidden="true">
            <p className="checkout-note">{'\u00a0'}</p>
          </div>
        </section>
      </main>
    )}
    >
      <WalletAuthorizePanel />
    </Suspense>
  );
}
