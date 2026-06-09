import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('company');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function CompanyPage() {
  return <LegalPage pageKey="company" page={page} />;
}
