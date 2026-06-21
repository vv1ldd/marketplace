const bitcoin = require('bitcoinjs-lib');
const { witnessStackToScriptWitness } = require('bitcoinjs-lib/src/psbt/psbtutils');
const { default: Verifier } = require('./node_modules/bip322-js/dist/Verifier.js');

async function readInput() {
  const chunks = [];
  for await (const chunk of process.stdin) {
    chunks.push(chunk);
  }

  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

function witnessBase64FromSignature(signature) {
  const value = String(signature || '').trim();
  if (!value) {
    return '';
  }

  if (value.startsWith('smp')) {
    return value.slice(3);
  }

  if (value.startsWith('ful')) {
    const tx = bitcoin.Transaction.fromBuffer(Buffer.from(value.slice(3), 'base64'));
    const witness = tx.ins[0]?.witness || [];
    if (!witness.length) {
      return '';
    }

    return witnessStackToScriptWitness(witness).toString('base64');
  }

  // Proof-of-funds signatures are not used for ownership binding yet.
  if (value.startsWith('pof')) {
    return '';
  }

  return value;
}

function verifySignatureForAddress(address, message, witnessBase64) {
  try {
    return Verifier.verifySignature(String(address), String(message), witnessBase64);
  } catch {
    return false;
  }
}

function verifyBitcoinMessage(address, message, signature) {
  const witnessBase64 = witnessBase64FromSignature(signature);
  if (!witnessBase64) {
    return false;
  }

  const candidates = [String(address), String(address).toLowerCase()];

  for (const candidate of [...new Set(candidates)]) {
    if (verifySignatureForAddress(candidate, message, witnessBase64)) {
      return true;
    }
  }

  return false;
}

(async () => {
  try {
    const { address, message, signature } = await readInput();

    if (!address || !message || !signature) {
      process.stdout.write('0');
      process.exit(1);
    }

    const ok = verifyBitcoinMessage(address, message, signature);
    process.stdout.write(ok ? '1' : '0');
    process.exit(ok ? 0 : 1);
  } catch {
    process.stdout.write('0');
    process.exit(1);
  }
})();
