import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('terms');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function TermsPage() {
  const { page, marketKey } = await loadLegalPage('terms');
  return <LegalPage pageKey="terms" page={page} marketKey={marketKey} />;
}
