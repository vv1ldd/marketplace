import Link from 'next/link';

export const dynamic = 'force-dynamic';

export default async function CabinetPage({ searchParams }) {
  const params = await searchParams;
  const query = new URLSearchParams(
    Object.entries(params || {}).filter(([, value]) => value !== undefined && value !== null),
  ).toString();

  return (
    <main className="page page--vault">
      <section className="hero hero--catalog-browse">
        <h1>Vault has moved closer.</h1>
        <p>Cabinet links now open through the unified Meanly Vault surface.</p>
        <div className="product-card__actions">
          <Link href={`/vault${query ? `?${query}` : ''}`}>Open Vault</Link>
        </div>
      </section>
    </main>
  );
}
