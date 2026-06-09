import Link from 'next/link';

export const dynamic = 'force-dynamic';

export default async function CabinetProjectionPage({ params, searchParams }) {
  await params;
  const query = await searchParams;
  const serializedQuery = new URLSearchParams(
    Object.entries(query || {}).filter(([, value]) => value !== undefined && value !== null),
  ).toString();

  return (
    <main className="page page--vault">
      <section className="hero hero--catalog-browse">
        <h1>Cabinet is now Vault.</h1>
        <p>Use the unified Vault surface for profile, orders, and secure codes.</p>
        <div className="product-card__actions">
          <Link href={`/vault${serializedQuery ? `?${serializedQuery}` : ''}`}>Open Vault</Link>
        </div>
      </section>
    </main>
  );
}
