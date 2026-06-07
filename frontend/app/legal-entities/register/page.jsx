import { BusinessRegistrationForm } from '../../../components/BusinessRegistrationForm';

export const dynamic = 'force-dynamic';

export default async function LegalEntityRegisterCompatPage() {
  return (
    <main className="page">
      <section className="hero">
        <h1>Add your company to Merchant Center.</h1>
        <p>
          Confirm work email, find the company by INN, and continue seller
          onboarding inside Meanly Merchant Center.
        </p>
      </section>
      <BusinessRegistrationForm />
    </main>
  );
}
