import { headers } from 'next/headers';
import { legalPage } from './legal-pages';
import { marketKeyForHost, resolveStorefrontHost } from './market-surface';

export async function loadLegalPage(pageKey) {
  const headerList = await headers();
  const host = await resolveStorefrontHost(headerList);
  const marketKey = marketKeyForHost(host);

  return {
    host,
    marketKey,
    page: legalPage(pageKey, marketKey),
  };
}
