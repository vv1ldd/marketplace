import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('company');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function CompanyPage() {
  const { page, marketKey } = await loadLegalPage('company');
  return <LegalPage pageKey="company" page={page} marketKey={marketKey} />;
}
