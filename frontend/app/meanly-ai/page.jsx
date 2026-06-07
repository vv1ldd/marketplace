import { MeanlyAiChat } from '../../components/MeanlyAiChat';

export const dynamic = 'force-dynamic';

export default async function MeanlyAiPage({ searchParams }) {
  const params = await searchParams;

  return (
    <main className="page page--ai">
      <MeanlyAiChat initialQuery={params?.q || ''} />
    </main>
  );
}
