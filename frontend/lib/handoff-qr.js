const TECHNICAL_QR_HOSTS = new Set([
  'pass.simplelayer.one',
  'localhost',
  '127.0.0.1',
]);

const QR_SCRIPT_SRC = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js';

function loadQrCode() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('QR generation is not available here.'));
  }

  if (window.QRCode) {
    return Promise.resolve(window.QRCode);
  }

  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${QR_SCRIPT_SRC}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve(window.QRCode), { once: true });
      existing.addEventListener('error', () => reject(new Error('Could not load QR generator.')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = QR_SCRIPT_SRC;
    script.async = true;
    script.onload = () => resolve(window.QRCode);
    script.onerror = () => reject(new Error('Could not load QR generator.'));
    document.head.appendChild(script);
  });
}

export function normalizeHandoffPayload(payload) {
  if (!payload?.qrUrl || typeof window === 'undefined') {
    return payload;
  }

  try {
    const qr = new URL(payload.qrUrl);
    const store = new URL(window.location.origin);
    const qrHost = qr.hostname.toLowerCase();

    if (!TECHNICAL_QR_HOSTS.has(qrHost) && qrHost === store.hostname.toLowerCase()) {
      return payload;
    }

    qr.protocol = store.protocol;
    qr.host = store.host;

    const fixedUrl = qr.toString();
    if (fixedUrl === payload.qrUrl) {
      return payload;
    }

    return {
      ...payload,
      qrUrl: fixedUrl,
      qrDataUrl: null,
    };
  } catch {
    return payload;
  }
}

export async function ensureHandoffQrDataUrl(payload) {
  const normalized = normalizeHandoffPayload(payload);

  if (normalized.qrDataUrl || !normalized.qrUrl) {
    return normalized;
  }

  try {
    const qrDataUrl = await ensureQrDataUrl(normalized.qrUrl);

    return {
      ...normalized,
      qrDataUrl,
    };
  } catch {
    return normalized;
  }
}

export async function ensureQrDataUrl(targetUrl, options = {}) {
  if (!targetUrl) {
    return '';
  }

  const QRCode = await loadQrCode();

  return QRCode.toDataURL(targetUrl, {
    width: options.width || 512,
    margin: options.margin ?? 1,
    errorCorrectionLevel: options.errorCorrectionLevel || 'L',
  });
}
