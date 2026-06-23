import QRCode from 'qrcode';

const TECHNICAL_QR_HOSTS = new Set([
  'pass.simplelayer.one',
  'localhost',
  '127.0.0.1',
]);

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

  return QRCode.toDataURL(targetUrl, {
    width: options.width || 512,
    margin: options.margin ?? 1,
    errorCorrectionLevel: options.errorCorrectionLevel || 'L',
  });
}
