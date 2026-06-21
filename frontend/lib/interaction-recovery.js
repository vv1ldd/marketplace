export function clearStuckInteractionLayers() {
  if (typeof document === 'undefined') {
    return;
  }

  document.querySelectorAll('.identity-center-surface.is-busy').forEach((node) => {
    node.classList.remove('is-busy');
  });

  document.querySelectorAll('.identity-center-state-stage.is-busy').forEach((node) => {
    node.classList.remove('is-busy');
  });

  document.querySelectorAll('.sl1-inline-handoff').forEach((node) => {
    if (!node.classList.contains('is-visible')) {
      node.setAttribute('aria-hidden', 'true');
    }
  });

  if (document.body) {
    document.body.style.pointerEvents = '';
    document.body.style.overflow = '';
    document.body.style.touchAction = '';
  }
}

export function bindInteractionRecovery() {
  if (typeof window === 'undefined') {
    return () => {};
  }

  const recover = () => {
    clearStuckInteractionLayers();
  };

  recover();
  window.addEventListener('pageshow', recover);
  window.addEventListener('focus', recover);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      recover();
    }
  });

  return () => {
    window.removeEventListener('pageshow', recover);
    window.removeEventListener('focus', recover);
  };
}
