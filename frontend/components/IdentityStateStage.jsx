'use client';

import { MeanlyLoadingMark } from './MeanlyLoadingMark';

export function IdentityStateStage({ stageKey, children, className = '', busy = false }) {
  return (
    <div
      className={`identity-center-state-stage ${busy ? 'is-busy' : ''} ${className}`.trim()}
      data-stage={stageKey}
    >
      {busy ? (
        <div className="identity-center-busy-overlay" aria-hidden="true">
          <MeanlyLoadingMark size="sm" />
        </div>
      ) : null}
      <div key={stageKey} className="identity-center-state-stage__panel">
        {children}
      </div>
    </div>
  );
}

export function IdentityStatusSlot({ error = '', status = '' }) {
  const visible = Boolean(error || status);
  const messageKey = error ? `error:${error}` : status ? `status:${status}` : 'empty';
  const message = error || status || '\u00a0';

  return (
    <div className={`identity-center-status ${visible ? 'is-visible' : ''}`} aria-live="polite">
      <div key={messageKey} className={`identity-center-status__content ${error ? 'identity-center-status__content--error' : ''}`}>
        <p>{message}</p>
      </div>
    </div>
  );
}
