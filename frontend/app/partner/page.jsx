import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function PartnerProjectionPage({ searchParams }) {
  await searchParams;
  redirect('/merchant');
}
