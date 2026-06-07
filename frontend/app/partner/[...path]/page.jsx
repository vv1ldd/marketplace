import { redirect } from 'next/navigation';
import { cookies } from 'next/headers';
import { backendUrl } from '../../../lib/storefront-api';
import { fetchPartnerWorkspaceWithCookie } from '../../../lib/partner-api';
import { PartnerWorkspace } from '../../../components/PartnerWorkspace';

export const dynamic = 'force-dynamic';

export default async function PartnerNestedProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  if (process.env.PARTNER_REACT_PRIMARY === 'false') {
    redirect(backendUrl(`/partner/${path.join('/')}`, await searchParams));
  }
  const cookieStore = await cookies();
  const cookieHeader = cookieStore.getAll().map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`).join('; ');
  const initialWorkspace = await fetchPartnerWorkspaceWithCookie(cookieHeader);

  return <PartnerWorkspace initialPath={path.join('/')} initialWorkspace={initialWorkspace} />;
}
