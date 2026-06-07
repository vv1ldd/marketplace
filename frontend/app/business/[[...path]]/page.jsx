import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { ProjectionSurface } from '../../../components/ProjectionSurface';
import { BusinessOfferForm } from '../../../components/BusinessOfferForm';
import { BusinessOnboardingStatus } from '../../../components/BusinessOnboardingStatus';
import { BusinessRegistrationForm } from '../../../components/BusinessRegistrationForm';
import { fetchBusinessOnboardingStatusWithCookie } from '../../../lib/business-api';

export const dynamic = 'force-dynamic';

function cookieHeader(cookieStore) {
  return cookieStore
    .getAll()
    .map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`)
    .join('; ');
}

export default async function BusinessProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  const normalizedPath = Array.isArray(path) ? path.join('/') : '';
  const shouldCheckOnboarding = ['', 'register', 'register/onboarding'].includes(normalizedPath);
  const onboardingPayload = shouldCheckOnboarding
    ? await fetchBusinessOnboardingStatusWithCookie(cookieHeader(await cookies()))
    : null;

  if (onboardingPayload?.redirect === '/partner') {
    redirect('/partner');
  }

  if (normalizedPath === 'register') {
    if (onboardingPayload?.legal_entity) {
      return (
        <main className="page">
          <BusinessOnboardingStatus initialPayload={onboardingPayload} />
        </main>
      );
    }

    return (
      <main className="page">
        <section className="hero">
          <h1>Add your company to Merchant Center.</h1>
          <p>
            Confirm work email, find the company by INN, and continue seller
            onboarding inside Meanly Merchant Center.
          </p>
        </section>
        <BusinessRegistrationForm initialApplicationChecked />
      </main>
    );
  }

  if (normalizedPath === 'register/offer') {
    return (
      <main className="page">
        <section className="hero">
          <h1>Sign the offer.</h1>
          <p>Review the Merchant Center agreement and confirm the signature with Meanly One.</p>
        </section>
        <BusinessOfferForm />
      </main>
    );
  }

  if (normalizedPath === 'register/onboarding') {
    return (
      <main className="page">
        <BusinessOnboardingStatus initialPayload={onboardingPayload} />
      </main>
    );
  }

  if (normalizedPath === '' && onboardingPayload?.legal_entity) {
    return (
      <main className="page">
        <BusinessOnboardingStatus initialPayload={onboardingPayload} />
      </main>
    );
  }

  return (
    <main className="page">
      <ProjectionSurface surface="business" path={path} searchParams={await searchParams} />
    </main>
  );
}
