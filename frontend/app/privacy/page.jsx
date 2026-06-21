import { LegalPage } from '../../components/LegalPage';
import { loadLegalPage } from '../../lib/legal-page-server';

export async function generateMetadata() {
  const { page } = await loadLegalPage('privacy');
  return {
    title: `${page.title} | Meanly`,
    description: page.description,
  };
}

export default async function PrivacyPage() {
  const { page, marketKey } = await loadLegalPage('privacy');
  return <LegalPage pageKey="privacy" page={page} marketKey={marketKey} />;
}
