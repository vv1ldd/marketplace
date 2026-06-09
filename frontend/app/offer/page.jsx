import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('offer');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function OfferPage() {
  return <LegalPage pageKey="offer" page={page} />;
}
