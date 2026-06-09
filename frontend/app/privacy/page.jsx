import { LegalPage } from '../../components/LegalPage';
import { legalPage } from '../../lib/legal-pages';

const page = legalPage('privacy');

export const metadata = {
  title: `${page.title} | Meanly`,
  description: page.description,
};

export default function PrivacyPage() {
  return <LegalPage pageKey="privacy" page={page} />;
}
