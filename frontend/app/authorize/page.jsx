import { NativeIdentityCenter } from '../../components/NativeIdentityCenter';

export const metadata = {
  title: 'Digital Safe · Meanly',
};

export default function AuthorizePage() {
  return (
    <main className="page page--identity-center page--identity-center--native">
      <NativeIdentityCenter />
    </main>
  );
}
