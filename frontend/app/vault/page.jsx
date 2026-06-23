import { StorefrontSessionPanel } from '../../components/StorefrontSessionPanel';
import { apiUrl, fetchWalletCoreBundle, VAULT_STOREFRONT_SCOPES } from '../../lib/storefront-api';
import { cookies } from 'next/headers';

function cookieHeader(cookieStore) {
  return cookieStore
    .getAll()
    .map((cookie) => `${cookie.name}=${encodeURIComponent(cookie.value)}`)
    .join('; ');
}

async function initialVaultSession(cookieStore) {
  const cookie = cookieHeader(cookieStore);
  if (!cookie) {
    return { vault: null, wallet: null, accessToken: null };
  }

  try {
    const issuedResponse = await fetch(`${apiUrl}/api/storefront/v1/identity/handoff`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Cookie: cookie,
      },
      body: JSON.stringify({ scopes: VAULT_STOREFRONT_SCOPES }),
      cache: 'no-store',
    });

    if (!issuedResponse.ok) {
      return { vault: null, wallet: null, accessToken: null };
    }

    const issued = await issuedResponse.json();
    const token = issued?.access_token;
    if (!token) {
      return { vault: null, wallet: null, accessToken: null };
    }

    const vaultResponse = await fetch(`${apiUrl}/api/storefront/v1/vault`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    if (!vaultResponse.ok) {
      return { vault: null, wallet: null, accessToken: null };
    }

    const vault = await vaultResponse.json();
    let wallet = null;

    try {
      wallet = await fetchWalletCoreBundle(token);
    } catch {
      wallet = null;
    }

    return { vault, wallet, accessToken: token };
  } catch {
    return { vault: null, wallet: null, accessToken: null };
  }
}

export default async function VaultPage({ searchParams }) {
  const params = await searchParams;
  const claimHandoff = params?.sl1_handoff === '1';
  const cookieStore = await cookies();
  const initialSession = claimHandoff
    ? { vault: null, wallet: null, accessToken: null }
    : await initialVaultSession(cookieStore);
  const initialVault = initialSession.vault;
  const initialWallet = initialSession.wallet;
  const initialAccessToken = initialSession.accessToken;
  const initialVaultAccessState = initialVault
    ? 'open'
    : claimHandoff
      ? 'checking'
      : 'closed';

  return (
    <main className="page page--vault">
      <StorefrontSessionPanel
        claimHandoff={claimHandoff}
        initialAccessToken={initialAccessToken}
        initialVault={initialVault}
        initialWallet={initialWallet}
        initialVaultAccessState={initialVaultAccessState}
      />
    </main>
  );
}
