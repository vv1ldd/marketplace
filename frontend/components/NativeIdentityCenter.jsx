'use client';

import { Suspense } from 'react';
import { IdentityStateStage } from './IdentityStateStage';
import { MeanlyLoadingMark } from './MeanlyLoadingMark';
import { WalletAuthorizePanel } from './WalletAuthorizePanel';
import { useLocale } from './LocaleProvider';

function AuthorizeFallback() {
  const { t } = useLocale();

  return (
    <section className="identity-center-surface identity-center-surface--native identity-center-surface--animated">
      <IdentityStateStage stageKey="loading">
        <div className="identity-center-surface__loading" aria-live="polite">
          <MeanlyLoadingMark label={t('sl1_connect_loading')} size="md" />
        </div>
      </IdentityStateStage>
    </section>
  );
}

export function NativeIdentityCenter() {
  return (
    <Suspense fallback={<AuthorizeFallback />}>
      <WalletAuthorizePanel />
    </Suspense>
  );
}
