'use client';

import { useState } from 'react';

function launchApp(deepLinkUrl, onlineUrl) {
  if (!deepLinkUrl) {
    return false;
  }

  window.location.assign(deepLinkUrl);
  return true;
}

export function MeanlyConnectLink({
  href,
  children,
  className,
  statusLabel = 'Opening Meanly One. If nothing opens, continue online with Meanly identity.',
  unavailableLabel = 'Meanly One app link is not available here. Continue online with the SL1 provider.',
  failureLabel = 'Meanly One handoff is unavailable here. Continue online with the SL1 provider.',
  onlineLabel = 'Continue online',
}) {
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

      if (!launchApp(payload.deep_link_url, fallbackUrl)) {
        setStatus(unavailableLabel);
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
