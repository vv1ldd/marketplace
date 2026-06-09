'use client';

import { createContext, useContext } from 'react';
import { translations } from '../lib/translations';

const LocaleContext = createContext({
  locale: 'en',
  t: (key) => key,
});

export function LocaleProvider({ locale = 'en', children }) {
  const activeLocale = ['ru', 'en'].includes(locale) ? locale : 'en';

  const t = (key, replacements = {}) => {
    let text = translations[activeLocale]?.[key] || translations['en']?.[key] || key;
    Object.entries(replacements).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, String(v));
    });
    return text;
  };

  return (
    <LocaleContext.Provider value={{ locale: activeLocale, t }}>
      {children}
    </LocaleContext.Provider>
  );
}

export function useLocale() {
  return useContext(LocaleContext);
}
