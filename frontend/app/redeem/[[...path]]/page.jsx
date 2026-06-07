import { ProjectionSurface } from '../../../components/ProjectionSurface';

export const dynamic = 'force-dynamic';

export default async function RedeemProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;

  return (
    <main className="page">
      <ProjectionSurface surface="redeem" path={path} searchParams={await searchParams} />
    </main>
  );
}
