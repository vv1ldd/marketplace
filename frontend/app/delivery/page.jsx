import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('delivery');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function DeliveryPage() {
  return <LegalPage pageKey="delivery" page={page} />;
}
