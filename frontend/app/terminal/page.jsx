import { ProjectionSurface } from '../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function TerminalProjectionPage({ searchParams }) {
  return (
    <main className="page">
      <ProjectionSurface surface="terminal" searchParams={await searchParams} />
    </main>
  );
}
