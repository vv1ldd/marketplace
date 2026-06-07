import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function CabinetProjectionPage({ params, searchParams }) {
  await params;
  const query = await searchParams;
  const serializedQuery = new URLSearchParams(
    Object.entries(query || {}).filter(([, value]) => value !== undefined && value !== null),
  ).toString();

  redirect(`/vault${serializedQuery ? `?${serializedQuery}` : ''}`);
}
