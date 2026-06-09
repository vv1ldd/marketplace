import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('terms');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function TermsPage() {
  return <LegalPage pageKey="terms" page={page} />;
}
