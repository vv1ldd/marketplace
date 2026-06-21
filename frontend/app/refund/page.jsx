import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('refund');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function RefundPage() {
  const { page, marketKey } = await loadLegalPage('refund');
  return <LegalPage pageKey="refund" page={page} marketKey={marketKey} />;
}
