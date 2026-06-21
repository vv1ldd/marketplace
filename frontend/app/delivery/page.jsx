import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('delivery');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function DeliveryPage() {
  const { page, marketKey } = await loadLegalPage('delivery');
  return <LegalPage pageKey="delivery" page={page} marketKey={marketKey} />;
}
