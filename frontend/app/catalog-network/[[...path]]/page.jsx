import { ProjectionSurface } from '../../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function CatalogNetworkProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;

  return (
    <main className="page">
      <ProjectionSurface surface="catalog-network" path={path} searchParams={await searchParams} />
    </main>
  );
}
