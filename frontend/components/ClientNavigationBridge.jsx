'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

function shouldHandleClick(event, anchor) {
  if (event.defaultPrevented || event.button !== 0) return false;
  if (event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) return false;
  if (anchor.target && anchor.target !== '_self') return false;
  if (anchor.hasAttribute('download')) return false;

  const href = anchor.getAttribute('href') || '';
  if (!href || href.startsWith('#')) return false;
  if (href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('simplel1:')) return false;

  const url = new URL(href, window.location.href);
  if (url.origin !== window.location.origin) return false;
  if (url.pathname === window.location.pathname && url.search === window.location.search) return false;

  return true;
}

export function ClientNavigationBridge() {
  const router = useRouter();

  useEffect(() => {
    const handleClick = (event) => {
      const anchor = event.target?.closest?.('a[href]');
      if (!anchor || !shouldHandleClick(event, anchor)) return;

      const url = new URL(anchor.getAttribute('href'), window.location.href);
      event.preventDefault();
      router.push(`${url.pathname}${url.search}${url.hash}`);
    };

    document.addEventListener('click', handleClick);
    return () => document.removeEventListener('click', handleClick);
  }, [router]);

  return null;
}
