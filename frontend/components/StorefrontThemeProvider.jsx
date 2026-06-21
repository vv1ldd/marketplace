'use client';

import { createContext, useContext, useEffect, useMemo } from 'react';
import { applyStorefrontTheme, DEFAULT_STOREFRONT_THEME } from '../lib/storefront-theme';

const StorefrontThemeContext = createContext({
  theme: DEFAULT_STOREFRONT_THEME,
});

export function useStorefrontTheme() {
  return useContext(StorefrontThemeContext);
}

export function StorefrontThemeProvider({ children }) {
  useEffect(() => {
    applyStorefrontTheme(DEFAULT_STOREFRONT_THEME, null);
  }, []);

  const value = useMemo(() => ({ theme: DEFAULT_STOREFRONT_THEME }), []);

  return (
    <StorefrontThemeContext.Provider value={value}>
      {children}
    </StorefrontThemeContext.Provider>
  );
}
