import { CatalogSurface } from '../components/CatalogSurface';
import { fetchStorefrontCatalog } from '../lib/storefront-api';

export const dynamic = 'force-dynamic';

function searchQuery(searchParams = {}) {
  const value = searchParams.q;
  return Array.isArray(value) ? value[0] || '' : value || '';
}

export default async function Home({ searchParams }) {
  const query = searchQuery(await searchParams);
  const initialCatalog = await fetchStorefrontCatalog(query).catch(() => null);

  return (
    <main className="page page--search-home">
      <CatalogSurface query={query} surface="home" initialCatalog={initialCatalog} />
    </main>
  );
}
