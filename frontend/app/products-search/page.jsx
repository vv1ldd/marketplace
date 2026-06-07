import { ProjectionSurface } from '../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function ProductsSearchProjectionPage({ searchParams }) {
  return (
    <main className="page">
      <ProjectionSurface surface="products-search" searchParams={await searchParams} />
    </main>
  );
}
