import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function CabinetPage({ searchParams }) {
  const params = await searchParams;
  const query = new URLSearchParams(
    Object.entries(params || {}).filter(([, value]) => value !== undefined && value !== null),
  ).toString();

  redirect(`/vault${query ? `?${query}` : ''}`);
}
