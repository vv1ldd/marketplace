import { StorefrontSessionPanel } from '../../components/StorefrontSessionPanel';
import { apiUrl } from '../../lib/storefront-api';
import { cookies } from 'next/headers';

const VAULT_SCOPES = [
  'storefront:read',
  'storefront:checkout',
  'storefront:vault',
  'storefront:partner-registration',
];

function cookieHeader(cookieStore) {
  return cookieStore
    .getAll()
    .map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`)
    .join('; ');
}

async function initialVaultFromSession(cookieStore) {
  const cookie = cookieHeader(cookieStore);
  if (!cookie) {
    return null;
  }

  try {
    const issuedResponse = await fetch(`${apiUrl}/api/storefront/v1/identity/handoff`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Cookie: cookie,
      },
      body: JSON.stringify({ scopes: VAULT_SCOPES }),
      cache: 'no-store',
    });

    if (!issuedResponse.ok) {
      return null;
    }

    const issued = await issuedResponse.json();
    const token = issued?.access_token;
    if (!token) {
      return null;
    }

    const vaultResponse = await fetch(`${apiUrl}/api/storefront/v1/vault`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    if (!vaultResponse.ok) {
      return null;
    }

    return vaultResponse.json();
  } catch {
    return null;
  }
}

export default async function VaultPage({ searchParams }) {
  const params = await searchParams;
  const claimHandoff = params?.sl1_handoff === '1';
  const cookieStore = await cookies();
  const initialVault = claimHandoff ? null : await initialVaultFromSession(cookieStore);
  const initialVaultAccessState = initialVault
    ? 'open'
    : claimHandoff
      ? 'checking'
      : 'closed';

  return (
    <main className="page page--vault">
      <StorefrontSessionPanel
        claimHandoff={claimHandoff}
        initialVault={initialVault}
        initialVaultAccessState={initialVaultAccessState}
      />
    </main>
  );
}
