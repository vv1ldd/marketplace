import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('offer');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function OfferPage() {
  const { page, marketKey } = await loadLegalPage('offer');
  return <LegalPage pageKey="offer" page={page} marketKey={marketKey} />;
}
