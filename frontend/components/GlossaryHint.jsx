'use client';

import { useEffect, useState } from 'react';

const STORAGE_KEY = 'meanly:glossary-hint-visits';
const VISIT_LIMIT = 10;

let recordedThisLoad = false;
let cachedVisibility = null;

function nextVisitVisibility() {
  if (cachedVisibility !== null) {
    return cachedVisibility;
  }

  if (typeof window === 'undefined') {
    return false;
  }

  try {
    const current = Number.parseInt(window.localStorage?.getItem(STORAGE_KEY) || '0', 10) || 0;
    const visits = recordedThisLoad ? current : current + 1;
    recordedThisLoad = true;
    window.localStorage?.setItem(STORAGE_KEY, String(visits));
    cachedVisibility = visits <= VISIT_LIMIT;
  } catch {
    cachedVisibility = true;
  }

  return cachedVisibility;
}

export function GlossaryHint({ children }) {
  const [isVisible, setIsVisible] = useState(false);
  const label = String(children || '').trim();

  useEffect(() => {
    setIsVisible(nextVisitVisibility());
  }, []);

  if (!isVisible || !label) {
    return null;
  }

  return (
    <span className="glossary-hint" title={label} aria-label={label}>
      ?
      <span className="glossary-hint__tooltip" role="tooltip">{label}</span>
    </span>
  );
}
