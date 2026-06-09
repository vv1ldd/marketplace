import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function PartnerNestedProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  await searchParams;
  const suffix = path.length > 0 ? `/${path.join('/')}` : '';
  redirect(`/merchant${suffix}`);
}
