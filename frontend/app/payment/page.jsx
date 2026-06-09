import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('payment');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function PaymentPage() {
  return <LegalPage pageKey="payment" page={page} />;
}
