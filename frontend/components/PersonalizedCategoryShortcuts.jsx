'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { fetchPersonalizedHome, storefrontTokenStorageKey } from '../lib/storefront-api';

function storedToken() {
  try {
    return window.localStorage.getItem(storefrontTokenStorageKey) || '';
  } catch {
    return '';
  }
}

export function PersonalizedCategoryShortcuts() {
  const [shortcuts, setShortcuts] = useState([]);

  useEffect(() => {
    const token = storedToken();
    if (!token) {
      return;
    }

    fetchPersonalizedHome(token)
      .then((payload) => setShortcuts(payload.category_shortcuts || []))
      .catch(() => setShortcuts([]));
  }, []);

  if (shortcuts.length === 0) {
    return null;
  }

  return (
    <section className="catalog-section personal-shortcuts">
      <div className="section-heading">
        <h2>Your quick paths</h2>
        <p>Based on purchases and saved products</p>
      </div>
      <div className="category-grid">
        {shortcuts.map((shortcut) => (
          <Link className="category-card category-card--personal" href={shortcut.href} key={shortcut.slug}>
            <strong>{shortcut.label}</strong>
            <span>{(shortcut.signals || []).join(' + ')}</span>
          </Link>
        ))}
      </div>
    </section>
  );
}
