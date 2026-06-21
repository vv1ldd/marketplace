import { backendUrl } from './storefront-api';

export function normalizeVaultUsername(value) {
  let username = String(value || '').trim().toLowerCase();
  if (!username) {
    return null;
  }

  username = username.replace(/^@+/, '');
  username = username.replace(/\.sl1\.one$/i, '');
  username = username.replace(/@(simplelayer\.one|sl1)$/i, '');
  if (username.includes('@')) {
    [username] = username.split('@', 2);
  }

  username = username.normalize('NFKD').replace(/[^\x00-\x7F]/g, '');
  username = username.replace(/[^a-z0-9._]+/g, '_');
  username = username.replace(/[._]{2,}/g, '_');
  username = username.replace(/^[._]+|[._]+$/g, '');
  username = username.slice(0, 32);

  if (!/^[a-z0-9][a-z0-9._]{2,31}$/.test(username)) {
    return null;
  }

  return username;
}

export async function checkVaultUsername(value) {
  const response = await fetch(backendUrl('/api/storefront/v1/identity/username/check'), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username: value }),
    cache: 'no-store',
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(payload.message || 'Username check failed.');
    error.status = response.status;
    throw error;
  }

  return payload;
}
