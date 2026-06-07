import { cookies } from 'next/headers';
import { PartnerRegistrationPanel } from '../../../components/PartnerRegistrationPanel';
import { fetchPartnerRegistrationState } from '../../../lib/storefront-api';
import { fetchBusinessOnboardingStatusWithCookie } from '../../../lib/business-api';

export const dynamic = 'force-dynamic';

function cookieHeader(cookieStore) {
  return cookieStore
    .getAll()
    .map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`)
    .join('; ');
}

export default async function PartnerRegisterPage({ searchParams }) {
  const params = await searchParams;
  const claimHandoff = params?.sl1_handoff === '1';
  const projection = await fetchPartnerRegistrationState();
  const onboardingPayload = await fetchBusinessOnboardingStatusWithCookie(cookieHeader(await cookies()));

  return (
    <main className="page">
      <section className="hero">
        <h1>Open Merchant Center.</h1>
        <p>
          Start from your existing identity. Browse stays public; Merchant Center
          opens after Meanly verifies who is starting the seller profile.
        </p>
      </section>

      <PartnerRegistrationPanel
        claimHandoff={claimHandoff}
        initialOnboardingChecked
        initialOnboardingPayload={onboardingPayload}
        initialProjection={projection}
      />
    </main>
  );
}
