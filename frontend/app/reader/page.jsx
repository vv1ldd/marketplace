import { ProjectionSurface } from '../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function ReaderProjectionPage({ searchParams }) {
  return (
    <main className="page">
      <ProjectionSurface surface="reader" searchParams={await searchParams} />
    </main>
  );
}
