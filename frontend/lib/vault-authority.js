'use client';

import { useEffect, useState } from 'react';
import { fetchVault, storefrontTokenStorageKey } from './storefront-api';

export const vaultAccessCookieName = 'meanly_vault_access';
export const vaultCacheStorageKey = 'meanly:vault-cache';
export const vaultAuthorityEventName = 'meanly:vault-authority';
export const vaultPreferredMethodStorageKey = 'meanly:vault-preferred-method';
export const vaultNativeAvailabilityStorageKey = 'meanly:native-app-availability';

export const VAULT_METHOD_BROWSER = 'web';
export const VAULT_NATIVE_COMING_SOON = 'coming-soon';

export function readStoredVaultToken() {
  try {
    return window.localStorage.getItem(storefrontTokenStorageKey) || '';
  } catch {
    return '';
  }
}

export function writeStoredVaultToken(token) {
  try {
    window.localStorage.setItem(storefrontTokenStorageKey, token);
  } catch {
    // Browser storage is only a convenience; backend session remains authority.
  }
}

export function readCachedVault() {
  try {
    return JSON.parse(window.sessionStorage.getItem(vaultCacheStorageKey) || 'null');
  } catch {
    return null;
  }
}

export function writeCachedVault(vault) {
  try {
    window.sessionStorage.setItem(vaultCacheStorageKey, JSON.stringify(vault));
    window.dispatchEvent(new CustomEvent(vaultAuthorityEventName, { detail: { vault } }));
  } catch {
    // Ignore storage failures; navigation can still resolve from a fresh token.
  }
}

export function rememberVaultBrowserPreference() {
  try {
    window.localStorage.setItem(vaultPreferredMethodStorageKey, VAULT_METHOD_BROWSER);
    document.cookie = `${vaultAccessCookieName}=open; Path=/; Max-Age=2592000; SameSite=Lax`;
  } catch {
    // Preference state only affects the next prompt; it is not auth authority.
  }
}

export function readVaultPreferredMethod() {
  try {
    return window.localStorage.getItem(vaultPreferredMethodStorageKey) || VAULT_METHOD_BROWSER;
  } catch {
    return VAULT_METHOD_BROWSER;
  }
}

export function readVaultNativeAvailability() {
  try {
    return window.localStorage.getItem(vaultNativeAvailabilityStorageKey) || VAULT_NATIVE_COMING_SOON;
  } catch {
    return VAULT_NATIVE_COMING_SOON;
  }
}

export function vaultMethodState() {
  return {
    preferredMethod: readVaultPreferredMethod(),
    nativeAvailability: readVaultNativeAvailability(),
    nativeComingSoon: readVaultNativeAvailability() === VAULT_NATIVE_COMING_SOON,
  };
}

export function clearVaultAuthorityState() {
  try {
    window.localStorage.removeItem(storefrontTokenStorageKey);
    window.sessionStorage.removeItem(vaultCacheStorageKey);
    document.cookie = `${vaultAccessCookieName}=; Path=/; Max-Age=0; SameSite=Lax`;
    window.dispatchEvent(new CustomEvent(vaultAuthorityEventName, { detail: { vault: null } }));
  } catch {
    // Nothing to clear if browser storage is unavailable.
  }
}

export function hasAuthoritySurface(vault, key) {
  return Array.isArray(vault?.authority_surfaces)
    && vault.authority_surfaces.some((surface) => surface?.key === key);
}

export function useHasAuthoritySurface(key) {
  const [hasSurface, setHasSurface] = useState(false);

  useEffect(() => {
    let isCancelled = false;

    const applyVault = (vault) => {
      if (!isCancelled) {
        setHasSurface(hasAuthoritySurface(vault, key));
      }
    };

    const resolve = async () => {
      const cached = readCachedVault();
      if (cached) {
        applyVault(cached);
      }

      const token = readStoredVaultToken();
      if (!token) {
        return;
      }

      try {
        const vault = await fetchVault(token);
        writeCachedVault(vault);
        applyVault(vault);
      } catch {
        if (!cached) {
          applyVault(null);
        }
      }
    };

    const handleAuthorityEvent = (event) => {
      applyVault(event.detail?.vault ?? readCachedVault());
    };

    const handleStorage = () => {
      applyVault(readCachedVault());
      resolve();
    };

    applyVault(readCachedVault());
    resolve();
    window.addEventListener(vaultAuthorityEventName, handleAuthorityEvent);
    window.addEventListener('storage', handleStorage);

    return () => {
      isCancelled = true;
      window.removeEventListener(vaultAuthorityEventName, handleAuthorityEvent);
      window.removeEventListener('storage', handleStorage);
    };
  }, [key]);

  return hasSurface;
}
