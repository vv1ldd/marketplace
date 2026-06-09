'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';

function sameOriginPath(url) {
  try {
    const parsed = new URL(url, window.location.origin);
    if (parsed.origin !== window.location.origin) {
      return null;
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return null;
  }
}

function launchApp(deepLinkUrl) {
  if (!deepLinkUrl) {
    return false;
  }

  window.location.assign(deepLinkUrl);
  return true;
}

function pollBrowserBoundHandoff(statusUrl, returnTo, onStatus) {
  if (!statusUrl) {
    return;
  }

  let attempts = 0;
  const timer = window.setInterval(async () => {
    attempts += 1;
    if (attempts > 120) {
      window.clearInterval(timer);
      return;
    }

    try {
      const response = await fetch(statusUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();
      if (payload.authenticated) {
        window.clearInterval(timer);
        onStatus('Meanly One approved. Continuing in this browser...');
        window.location.assign(payload.redirect_url || returnTo || '/vault');
      }
    } catch {
      // Keep polling while native app is in front.
    }
  }, 1000);
}

export function MeanlyConnectLink({
  href,
  children,
  className,
  statusLabel = 'Opening Meanly One. If nothing opens, return here or use browser fallback.',
  failureLabel = 'Meanly One handoff is unavailable here. Continue in browser.',
  onlineLabel = 'Open in browser',
}) {
  const router = useRouter();
  const [status, setStatus] = useState('');
  const [onlineUrl, setOnlineUrl] = useState('');

  async function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();
    setStatus(statusLabel);

    try {
      const response = await fetch(href, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Meanly handoff failed.');
      }

      const payload = await response.json();
      const fallbackUrl = payload.redirect_url || href;
      setOnlineUrl(fallbackUrl);

      if (payload.deep_link_url) {
        launchApp(payload.deep_link_url);
        pollBrowserBoundHandoff(payload.status_url, payload.return_to, setStatus);
      } else {
        const localRedirect = sameOriginPath(fallbackUrl);
        if (localRedirect) {
          router.push(localRedirect);
          return;
        }
        window.location.assign(fallbackUrl);
      }
    } catch {
      setOnlineUrl(href);
      setStatus(failureLabel);
    }
  }

  return (
    <>
      <a className={className} href={href} onClick={onClick}>
        {children}
      </a>
      {status ? (
        <span className="meanly-connect-status">
          {status}
          {onlineUrl ? (
            <>
              {' '}
              <a href={onlineUrl}>{onlineLabel}</a>
            </>
          ) : null}
        </span>
      ) : null}
    </>
  );
}
