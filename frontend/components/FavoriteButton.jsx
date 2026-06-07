'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { storefrontTokenStorageKey, toggleFavorite } from '../lib/storefront-api';

function storedToken() {
  try {
    return window.localStorage.getItem(storefrontTokenStorageKey) || '';
  } catch {
    return '';
  }
}

export function FavoriteButton({ product }) {
  const router = useRouter();
  const [favorite, setFavorite] = useState(false);
  const [busy, setBusy] = useState(false);

  async function onToggle(event) {
    event.preventDefault();
    event.stopPropagation();

    const token = storedToken();
    if (!token) {
      router.push('/login');
      return;
    }

    setBusy(true);
    try {
      const payload = await toggleFavorite(product, token);
      setFavorite(Boolean(payload.favorite));
    } finally {
      setBusy(false);
    }
  }

  return (
    <button
      aria-label={favorite ? 'Remove from favorites' : 'Save product'}
      className={`favorite-button${favorite ? ' is-active' : ''}`}
      disabled={busy}
      onClick={onToggle}
      title={favorite ? 'Remove from favorites' : 'Connect with Meanly to save'}
      type="button"
    >
      {favorite ? '★' : '☆'}
    </button>
  );
}
