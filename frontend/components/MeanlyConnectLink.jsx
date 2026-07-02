'use client';

import { useState } from 'react';
import { handleSimpleL1ConnectClick, isSimpleL1ConnectHref } from '../lib/simple-l1-connect-popup';

export function MeanlyConnectLink({
  href,
  children,
  className,
  statusLabel = 'Preparing secure sign-in...',
  progressLabel = 'Waiting for confirmation in Meanly One...',
  completeLabel = 'Confirmed. Opening Vault...',
  failureLabel = 'Could not open Meanly One. Try again.',
}) {
  const [status, setStatus] = useState('');

  function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    if (!isSimpleL1ConnectHref(href)) {
      return;
    }

    setStatus(statusLabel);

    const opened = handleSimpleL1ConnectClick(event, href, (signal) => {
      if (signal.phase === 'READY') {
        setStatus(statusLabel);
      } else if (signal.phase === 'PROGRESS') {
        setStatus(progressLabel);
      } else if (signal.phase === 'COMPLETE') {
        setStatus(completeLabel);
      }
    });
    if (!opened) {
      setStatus(failureLabel);
    }
  }

  return (
    <>
      <a className={className} href={href} onClick={onClick}>
        {children}
      </a>
      {status ? <span className="meanly-connect-status">{status}</span> : null}
    </>
  );
}
