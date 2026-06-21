import { IdentityConnectLauncher } from '../../../components/IdentityConnectLauncher';

export const metadata = {
  title: 'Digital Safe · Meanly',
};

export default async function VaultConnectPage({ searchParams }) {
  const params = await searchParams;

  return (
    <main className="page page--identity-center page--identity-center--launcher">
      <IdentityConnectLauncher params={params} />
    </main>
  );
}
