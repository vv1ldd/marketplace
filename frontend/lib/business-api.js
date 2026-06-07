import { apiUrl } from './storefront-api';

async function parseJson(response) {
  const text = await response.text();
  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

export async function fetchBusinessOnboardingStatusWithCookie(cookieHeader = '') {
  return fetch(new URL('/business/register/onboarding', apiUrl), {
    headers: {
      Accept: 'application/json',
      Cookie: cookieHeader,
    },
    cache: 'no-store',
  }).then(async (response) => {
    const payload = await parseJson(response);
    if (!response.ok) {
      return null;
    }

    return payload;
  }).catch(() => null);
}
