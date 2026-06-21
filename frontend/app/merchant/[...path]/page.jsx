import { cookies, headers } from 'next/headers';
import { fetchPartnerWorkspaceWithCookie } from '../../../lib/partner-api';
import { normalizePartnerWorkspace } from '../../../lib/merchant-workspace-market';
import { PartnerWorkspace } from '../../../components/PartnerWorkspace';

export const dynamic = 'force-dynamic';

async function loadInitialWorkspace() {
  const cookieStore = await cookies();
  const requestHeaders = await headers();
  const cookieHeader = cookieStore.getAll().map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`).join('; ');
  const forwardedHost = requestHeaders.get('x-forwarded-host') || requestHeaders.get('host') || '';
  const payload = await fetchPartnerWorkspaceWithCookie(cookieHeader, forwardedHost);

  return normalizePartnerWorkspace(payload);
}

export default async function MerchantNestedProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  await searchParams;
  const initialWorkspace = await loadInitialWorkspace();

  return <PartnerWorkspace initialPath={path.join('/')} initialWorkspace={initialWorkspace} />;
}
