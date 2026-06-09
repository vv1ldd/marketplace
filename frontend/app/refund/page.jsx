import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('refund');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function RefundPage() {
  return <LegalPage pageKey="refund" page={page} />;
}
