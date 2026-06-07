import { ProjectionSurface } from '../../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function StoreProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;

  return (
    <main className="page">
      <ProjectionSurface surface="store" path={path} searchParams={await searchParams} />
    </main>
  );
}
