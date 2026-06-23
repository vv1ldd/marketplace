import { HDKey } from '@scure/bip32';
import { mnemonicToSeedSync, validateMnemonic } from '@scure/bip39';
import { wordlist } from '@scure/bip39/wordlists/english.js';
import { p2wpkh } from '@scure/btc-signer/payment.js';
import { secp256k1 } from '@noble/curves/secp256k1';
import { keccak_256 } from '@noble/hashes/sha3';
import * as ed25519 from '@noble/ed25519';
import bs58 from 'bs58';
import { derivePath } from 'ed25519-hd-key';
import { mnemonicValidate, mnemonicToPrivateKey } from '@ton/crypto';
import { WalletContractV4 } from '@ton/ton';

const MANAGED_IMPORT_PROFILES = {
  polygon: { kind: 'evm', path: "m/44'/60'/0'/0/0", secretFormat: 'evm_private_key_hex' },
  ethereum: { kind: 'evm', path: "m/44'/60'/0'/0/0", secretFormat: 'evm_private_key_hex' },
  base: { kind: 'evm', path: "m/44'/60'/0'/0/0", secretFormat: 'evm_private_key_hex' },
  bitcoin: { kind: 'bitcoin', path: "m/84'/0'/0'/0/0", secretFormat: 'bitcoin_private_key_hex' },
  solana: { kind: 'solana', path: "m/44'/501'/0'/0'", secretFormat: 'solana_secret_key_base64' },
  ton: { kind: 'ton', secretFormat: 'ton_secret_key_base64' },
};

function bytesToHex(bytes) {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function bytesToBase64(bytes) {
  const array = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
  let binary = '';
  array.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary);
}

function normalizeMnemonic(mnemonic) {
  return mnemonic.trim().toLowerCase().replace(/\s+/g, ' ');
}

export function supportsManagedSeedImport(bindingKey) {
  return Object.prototype.hasOwnProperty.call(MANAGED_IMPORT_PROFILES, bindingKey);
}

export async function deriveManagedWalletFromMnemonic(bindingKey, mnemonic) {
  const profile = MANAGED_IMPORT_PROFILES[bindingKey];
  if (!profile) {
    throw new Error('Managed seed import is not available for this network.');
  }

  const normalized = normalizeMnemonic(mnemonic);
  if (!normalized) {
    throw new Error('Enter your recovery phrase.');
  }

  if (profile.kind === 'ton') {
    return deriveTonMaterial(normalized, profile);
  }

  if (!validateMnemonic(normalized, wordlist)) {
    throw new Error('Recovery phrase is not valid.');
  }

  const seed = mnemonicToSeedSync(normalized);

  if (profile.kind === 'evm') {
    return deriveEvmMaterial(seed, profile);
  }

  if (profile.kind === 'bitcoin') {
    return deriveBitcoinMaterial(seed, profile);
  }

  if (profile.kind === 'solana') {
    return deriveSolanaMaterial(seed, profile);
  }

  throw new Error('Managed seed import is not available for this network.');
}

function deriveEvmMaterial(seed, profile) {
  const child = HDKey.fromMasterSeed(seed).derive(profile.path);
  if (!child.privateKey) {
    throw new Error('Could not derive an EVM key from this recovery phrase.');
  }

  const privateKeyHex = bytesToHex(child.privateKey);
  const publicKey = secp256k1.getPublicKey(child.privateKey, false);
  const address = `0x${bytesToHex(keccak_256(publicKey.slice(1)).slice(-20))}`;

  return {
    address,
    secret: privateKeyHex,
    secretFormat: profile.secretFormat,
  };
}

function deriveBitcoinMaterial(seed, profile) {
  const child = HDKey.fromMasterSeed(seed).derive(profile.path);
  if (!child.privateKey) {
    throw new Error('Could not derive a Bitcoin key from this recovery phrase.');
  }

  const publicKey = secp256k1.getPublicKey(child.privateKey, true);
  const address = p2wpkh(publicKey).address;
  if (!address) {
    throw new Error('Could not derive a Bitcoin address from this recovery phrase.');
  }

  return {
    address,
    secret: bytesToHex(child.privateKey),
    secretFormat: profile.secretFormat,
  };
}

async function deriveSolanaMaterial(seed, profile) {
  const { key } = derivePath(profile.path, seed.toString('hex'));
  const publicKey = await ed25519.getPublicKeyAsync(key);
  const secretKey = new Uint8Array(64);
  secretKey.set(key);
  secretKey.set(publicKey, 32);

  return {
    address: bs58.encode(publicKey),
    secret: bytesToBase64(secretKey),
    secretFormat: profile.secretFormat,
  };
}

async function deriveTonMaterial(normalizedMnemonic, profile) {
  const words = normalizedMnemonic.split(' ');

  if (await mnemonicValidate(words)) {
    const keyPair = await mnemonicToPrivateKey(words);
    const wallet = WalletContractV4.create({ workchain: 0, publicKey: keyPair.publicKey });

    return {
      address: wallet.address.toString({ bounceable: false, urlSafe: true }),
      secret: bytesToBase64(keyPair.secretKey),
      secretFormat: profile.secretFormat,
    };
  }

  throw new Error('Recovery phrase is not a valid TON wallet mnemonic.');
}
