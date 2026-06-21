function encodeBase64(bytes) {
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(bytes).toString('base64');
  }

  let binary = '';
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }

  return btoa(binary);
}

function decodeBase64(value) {
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(value, 'base64');
  }

  const binary = atob(value);
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes;
}

const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

function decodeBase58(value) {
  let num = 0n;

  for (const character of value) {
    const index = BASE58_ALPHABET.indexOf(character);
    if (index === -1) {
      return null;
    }

    num = num * 58n + BigInt(index);
  }

  let hex = num.toString(16);
  if (hex.length % 2 !== 0) {
    hex = `0${hex}`;
  }

  let leadingZeros = 0;
  for (const character of value) {
    if (character === '1') {
      leadingZeros += 1;
      continue;
    }

    break;
  }

  const bytes = [];
  for (let index = 0; index < hex.length; index += 2) {
    bytes.push(parseInt(hex.slice(index, index + 2), 16));
  }

  return new Uint8Array([...Array(leadingZeros).fill(0), ...bytes]);
}

export function normalizeSolanaSignature(signature) {
  if (!signature) {
    return '';
  }

  if (signature instanceof Uint8Array) {
    return encodeBase64(signature);
  }

  const value = String(signature).trim();
  if (!value) {
    return '';
  }

  if (value.startsWith('0x')) {
    const bytes = decodeBase64(encodeBase64(
      Uint8Array.from(value.slice(2).match(/.{1,2}/g)?.map((part) => parseInt(part, 16)) || []),
    ));
    return encodeBase64(bytes);
  }

  try {
    const base64Bytes = decodeBase64(value);
    if (base64Bytes.length === 64) {
      return value;
    }
  } catch {
    // fall through
  }

  const base58Bytes = decodeBase58(value);
  if (base58Bytes && base58Bytes.length === 64) {
    return encodeBase64(base58Bytes);
  }

  return value;
}
