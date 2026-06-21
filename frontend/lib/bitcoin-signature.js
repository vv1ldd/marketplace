export function bytesToBase64(value) {
  if (typeof value === 'string') {
    return value;
  }

  if (value instanceof Uint8Array || Array.isArray(value)) {
    if (typeof Buffer !== 'undefined') {
      return Buffer.from(value).toString('base64');
    }

    const binary = Array.from(value, (byte) => String.fromCharCode(byte)).join('');
    return btoa(binary);
  }

  if (value instanceof ArrayBuffer) {
    return bytesToBase64(new Uint8Array(value));
  }

  if (
    value
    && typeof value === 'object'
    && typeof value.byteLength === 'number'
    && value.buffer instanceof ArrayBuffer
  ) {
    return bytesToBase64(new Uint8Array(value.buffer, value.byteOffset, value.byteLength));
  }

  return '';
}

function decodeHexSignature(value) {
  const normalized = String(value || '').trim();
  if (!normalized || !/^0x?[0-9a-f]+$/i.test(normalized)) {
    return '';
  }

  const hex = normalized.startsWith('0x') ? normalized.slice(2) : normalized;
  if (hex.length % 2 !== 0) {
    return '';
  }

  if (typeof Buffer !== 'undefined') {
    return Buffer.from(hex, 'hex').toString('base64');
  }

  const bytes = hex.match(/.{1,2}/g)?.map((part) => String.fromCharCode(parseInt(part, 16))) || [];
  return btoa(bytes.join(''));
}

export function normalizeBitcoinSignature(result) {
  if (!result) {
    return '';
  }

  if (typeof result === 'string') {
    const trimmed = result.trim();
    if (
      trimmed.startsWith('smp')
      || trimmed.startsWith('ful')
      || trimmed.startsWith('pof')
    ) {
      return trimmed;
    }

    if (trimmed.startsWith('0x') || (/^[0-9a-f]+$/i.test(trimmed) && trimmed.length > 130)) {
      return decodeHexSignature(trimmed);
    }

    return trimmed;
  }

  const entry = Array.isArray(result) ? result[0] : result;
  if (!entry) {
    return '';
  }

  if (typeof entry === 'string') {
    return normalizeBitcoinSignature(entry);
  }

  if (typeof entry.signature === 'string') {
    return normalizeBitcoinSignature(entry.signature);
  }

  if (entry.signature && typeof entry.signature === 'object') {
    if (entry.signature instanceof Uint8Array || entry.signature instanceof ArrayBuffer) {
      return bytesToBase64(entry.signature);
    }

    return normalizeBitcoinSignature(entry.signature);
  }

  if (typeof entry.signedMessage === 'string') {
    return normalizeBitcoinSignature(entry.signedMessage);
  }

  if (entry.signedMessage && typeof entry.signedMessage === 'object') {
    if (entry.signedMessage instanceof Uint8Array || entry.signedMessage instanceof ArrayBuffer) {
      return bytesToBase64(entry.signedMessage);
    }

    return normalizeBitcoinSignature(entry.signedMessage);
  }

  const binary = entry;
  return bytesToBase64(binary);
}
