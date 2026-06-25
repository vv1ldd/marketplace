import { fetchVault, simpleL1ConnectUrl, vaultHandoffUrl } from './storefront-api';
import { authorizePathFromRedirect, resolveSimpleL1ConnectHandoff } from './simple-l1-identity-center';
import { clearVaultAuthorityState, readStoredVaultToken, writeCachedVault } from './vault-authority';

export function buildVaultConnectUrl({
  returnTo,
  intentTitle,
  intentCta,
  intentDescription,
  mode = 'connect',
} = {}) {
  const resolvedReturnTo = returnTo || vaultHandoffUrl();

  return simpleL1ConnectUrl({
    returnTo: resolvedReturnTo,
    mode,
    intentTitle,
    intentCta,
    intentDescription,
  });
}

function connectQueryFromIntent({
  returnTo,
  intentTitle,
  intentCta,
  intentDescription,
  mode = 'connect',
} = {}) {
  return {
    return_to: returnTo || vaultHandoffUrl(),
    mode,
    intent_type: 'meanly.vault.open',
    intent_title: intentTitle,
    intent_description: intentDescription,
    intent_cta: intentCta,
  };
}

export async function openKnownVault(router) {
  const token = readStoredVaultToken();
  if (!token) {
    return false;
  }

  try {
    const vault = await fetchVault(token);
    writeCachedVault(vault);
    router.push('/vault');
    return true;
  } catch (exception) {
    if ([401, 403].includes(exception.status)) {
      clearVaultAuthorityState();
    }

    return false;
  }
}

export async function navigateToVaultEntry(router, {
  returnTo,
  intentTitle,
  intentCta,
  intentDescription,
} = {}) {
  if (await openKnownVault(router)) {
    return;
  }

  const connectQuery = connectQueryFromIntent({
    returnTo,
    intentTitle,
    intentCta,
    intentDescription,
  });
  const connectUrl = buildVaultConnectUrl({
    returnTo,
    intentTitle,
    intentCta,
    intentDescription,
  });

  try {
    const handoff = await resolveSimpleL1ConnectHandoff(connectQuery);

    if (handoff.showHandoff) {
      router.push(connectUrl);
      return;
    }

    if (handoff.externalUrl) {
      window.location.assign(handoff.externalUrl);
      return;
    }

    router.push(handoff.authorizePath);
  } catch {
    try {
      router.push(connectUrl);
    } catch {
      window.location.assign(connectUrl);
    }
  }
}

export function isVaultSurfacePath(pathname = '/') {
  return pathname.startsWith('/vault')
    || pathname.startsWith('/authorize')
    || pathname.startsWith('/wallet');
}

export { authorizePathFromRedirect };
