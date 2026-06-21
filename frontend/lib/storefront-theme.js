export const DEFAULT_STOREFRONT_THEME = 'retro';
export const PREVIEW_HOLIDAY_ID = 'national-unity';

const SL1_THEME_MAP = {
  retro: 'neobrutalism',
  consortium: 'neobrutalism',
  nordic: 'light',
  synthwave: 'dark',
  carbon: 'dark',
  partner: 'dark',
  neobrutalism: 'neobrutalism',
  dark: 'dark',
  light: 'light',
};

export function mapStorefrontThemeToSl1(theme = DEFAULT_STOREFRONT_THEME) {
  return SL1_THEME_MAP[theme] || 'neobrutalism';
}

function readCookie(name) {
  if (typeof document === 'undefined') {
    return null;
  }

  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name, value, days = 365) {
  if (typeof document === 'undefined') {
    return;
  }

  const expires = new Date(Date.now() + days * 86400000).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires};path=/;SameSite=Lax`;
}

export function readHolidayPreference() {
  return readCookie('holiday');
}

export function isHolidayPreferenceEnabled(preference) {
  if (!preference || preference === 'auto') {
    return true;
  }

  return preference !== 'none';
}

export function resolveActiveHolidayId() {
  if (typeof document === 'undefined') {
    return null;
  }

  const preference = readHolidayPreference();
  if (preference === 'none') {
    return null;
  }

  return document.body?.getAttribute('data-holiday')
    || document.querySelector('[data-sl1-inline-handoff]')?.getAttribute('data-holiday')
    || window.__meanlyActiveHoliday
    || (preference && preference !== 'auto' && preference !== 'none' ? preference : null)
    || (window.__meanlyHolidayPreviewEnabled ? PREVIEW_HOLIDAY_ID : null);
}

export function writeHolidayPreference(value) {
  if (!value || value === 'auto') {
    document.cookie = 'holiday=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
    return;
  }

  writeCookie('holiday', value);
}

export function applyStorefrontTheme(theme = DEFAULT_STOREFRONT_THEME, holidayId = null) {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.setAttribute('data-theme', theme);

  if (document.body) {
    document.body.setAttribute('data-theme', theme);
    if (holidayId) {
      document.body.setAttribute('data-holiday', holidayId);
    } else {
      document.body.removeAttribute('data-holiday');
    }
  }

  document.querySelectorAll('[data-sl1-inline-handoff]').forEach((overlay) => {
    if (holidayId) {
      overlay.setAttribute('data-holiday', holidayId);
    } else {
      overlay.removeAttribute('data-holiday');
    }
  });
}

export async function fetchActiveHoliday() {
  const preference = readHolidayPreference();
  const params = new URLSearchParams();
  if (preference && preference !== 'auto') {
    params.set('holiday', preference);
  }

  const query = params.toString();
  const path = query ? `/backend/api/holidays/active?${query}` : '/backend/api/holidays/active';
  const response = await fetch(path, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error('Holiday API failed');
  }

  const payload = await response.json();
  if (preference === 'none') {
    return null;
  }

  return payload?.active_holiday?.id
    || (preference && preference !== 'auto' && preference !== 'none' ? preference : null);
}
