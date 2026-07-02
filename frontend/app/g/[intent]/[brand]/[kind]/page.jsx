import { permanentRedirect } from 'next/navigation';
import { groupCatalogPath } from '../../../../lib/catalog-urls';
import { queryObject } from '../../../../lib/group-page';

export const dynamic = 'force-dynamic';

export default async function LegacyGroupCatalogRedirect({ params, searchParams }) {
  const { intent, brand, kind } = await params;
  const query = queryObject(await searchParams);

  permanentRedirect(groupCatalogPath(intent, brand, kind, query));
}
