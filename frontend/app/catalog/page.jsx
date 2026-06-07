import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

function searchQuery(searchParams = {}) {
  const value = searchParams.q;
  return Array.isArray(value) ? value[0] || '' : value || '';
}

export default async function CatalogPage({ searchParams }) {
  const query = searchQuery(await searchParams);
  redirect(query ? `/?q=${encodeURIComponent(query)}` : '/');
}
