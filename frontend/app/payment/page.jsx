import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('payment');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function PaymentPage() {
  const { page, marketKey } = await loadLegalPage('payment');
  return <LegalPage pageKey="payment" page={page} marketKey={marketKey} />;
}
