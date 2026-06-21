/**
 * True when this browser is already the phone/tablet users would scan a QR with.
 * Cross-device handoff ("continue on phone") only makes sense on desktop/laptop.
 */
export function isPrimaryMobileDevice() {
  if (typeof window === 'undefined') {
    return false;
  }

  const ua = navigator.userAgent || '';
  const isPhoneUa = /Android.*Mobile|iPhone|iPod|IEMobile|Opera Mini/i.test(ua);
  const isTabletUa = /iPad|Tablet|PlayBook|Silk/i.test(ua)
    || (/Android/i.test(ua) && !/Mobile/i.test(ua));

  if (isPhoneUa || isTabletUa) {
    return true;
  }

  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  const noHover = window.matchMedia('(hover: none)').matches;
  const narrowViewport = window.matchMedia('(max-width: 760px)').matches;

  return narrowViewport && coarsePointer && noHover;
}

export function supportsCrossDeviceHandoff({ handoffId = '' } = {}) {
  if (handoffId) {
    return false;
  }

  return !isPrimaryMobileDevice();
}
