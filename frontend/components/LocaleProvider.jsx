'use client';

import { createContext, useCallback, useContext, useMemo } from 'react';
import { translations } from '../lib/translations';

const LocaleContext = createContext({
  locale: 'en',
  t: (key) => key,
});

export function LocaleProvider({ locale = 'en', children }) {
  const activeLocale = ['ru', 'en'].includes(locale) ? locale : 'en';

  const t = useCallback((key, replacements = {}) => {
    let text = translations[activeLocale]?.[key] || translations['en']?.[key] || key;
    Object.entries(replacements).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, String(v));
    });
    return text;
  }, [activeLocale]);

  const value = useMemo(() => ({ locale: activeLocale, t }), [activeLocale, t]);

  return (
    <LocaleContext.Provider value={value}>
      {children}
    </LocaleContext.Provider>
  );
}

export function useLocale() {
  return useContext(LocaleContext);
}
