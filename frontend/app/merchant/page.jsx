import { cookies } from 'next/headers';
import { fetchPartnerWorkspaceWithCookie } from '../../lib/partner-api';
import { PartnerWorkspace } from '../../components/PartnerWorkspace';

export const dynamic = 'force-dynamic';

export default async function MerchantProjectionPage({ searchParams }) {
  await searchParams;
  const cookieStore = await cookies();
  const cookieHeader = cookieStore.getAll().map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`).join('; ');
  const initialWorkspace = await fetchPartnerWorkspaceWithCookie(cookieHeader);

  return <PartnerWorkspace initialWorkspace={initialWorkspace} />;
}
