<?php

namespace App\Services\ManagedWallet;

use App\Support\BitcoinAddressCodec;
use App\Support\SolanaAddressCodec;
use App\Support\TonAddressCodec;
use App\Support\TonWalletAddressDeriver;
use Elliptic\EC;
use Illuminate\Validation\ValidationException;
use kornrunner\Keccak;

class ManagedWalletSecretValidator
{
    public function __construct(
        private readonly SolanaAddressCodec $solanaAddresses,
        private readonly BitcoinAddressCodec $bitcoinAddresses,
        private readonly TonAddressCodec $tonAddresses,
        private readonly TonWalletAddressDeriver $tonWalletAddresses,
    ) {}

    /**
     * @return array{address: string, secret: string, secret_format: string}
     */
    public function validate(string $protocol, string $address, string $secret, string $secretFormat): array
    {
        $protocol = strtolower(trim($protocol));
        $address = trim($address);
        $secret = trim($secret);
        $secretFormat = trim($secretFormat);

        if ($address === '' || $secret === '') {
            throw ValidationException::withMessages([
                'secret' => 'Managed wallet import requires an address and secret.',
            ]);
        }

        return match ($protocol) {
            'evm' => $this->validateEvm($address, $secret, $secretFormat),
            'solana' => $this->validateSolana($address, $secret, $secretFormat),
            'utxo' => $this->validateBitcoin($address, $secret, $secretFormat),
            'ton' => $this->validateTon($address, $secret, $secretFormat),
            default => throw ValidationException::withMessages([
                'binding_key' => 'Managed wallet import is not available for this network protocol.',
            ]),
        };
    }

    /**
     * @return array{address: string, secret: string, secret_format: string}
     */
    private function validateEvm(string $address, string $secret, string $secretFormat): array
    {
        if ($secretFormat !== 'evm_private_key_hex') {
            throw ValidationException::withMessages([
                'secret_format' => 'Unsupported EVM secret format.',
            ]);
        }

        $privateKeyHex = strtolower(preg_replace('/^0x/', '', $secret) ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $privateKeyHex)) {
            throw ValidationException::withMessages([
                'secret' => 'EVM import requires a 32-byte private key.',
            ]);
        }

        $derived = $this->deriveEvmAddress($privateKeyHex);
        if (strcasecmp($derived, $address) !== 0) {
            throw ValidationException::withMessages([
                'address' => 'Address does not match the imported EVM secret.',
            ]);
        }

        return [
            'address' => strtolower($derived),
            'secret' => $privateKeyHex,
            'secret_format' => 'evm_private_key_hex',
        ];
    }

    /**
     * @return array{address: string, secret: string, secret_format: string}
     */
    private function validateSolana(string $address, string $secret, string $secretFormat): array
    {
        if ($secretFormat !== 'solana_secret_key_base64') {
            throw ValidationException::withMessages([
                'secret_format' => 'Unsupported Solana secret format.',
            ]);
        }

        $secretKey = base64_decode($secret, true);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw ValidationException::withMessages([
                'secret' => 'Solana import requires a 64-byte secret key.',
            ]);
        }

        $publicKey = sodium_crypto_sign_publickey_from_secretkey($secretKey);
        $derived = $this->solanaAddresses->encodeAddress($publicKey);
        if ($derived !== $address) {
            throw ValidationException::withMessages([
                'address' => 'Address does not match the imported Solana secret.',
            ]);
        }

        return [
            'address' => $derived,
            'secret' => base64_encode($secretKey),
            'secret_format' => 'solana_secret_key_base64',
        ];
    }

    /**
     * @return array{address: string, secret: string, secret_format: string}
     */
    private function validateBitcoin(string $address, string $secret, string $secretFormat): array
    {
        if ($secretFormat !== 'bitcoin_private_key_hex') {
            throw ValidationException::withMessages([
                'secret_format' => 'Unsupported Bitcoin secret format.',
            ]);
        }

        $privateKeyHex = strtolower(preg_replace('/^0x/', '', $secret) ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $privateKeyHex)) {
            throw ValidationException::withMessages([
                'secret' => 'Bitcoin import requires a 32-byte private key.',
            ]);
        }

        $ec = new EC('secp256k1');
        $publicKeyHex = $ec->keyFromPrivate($privateKeyHex)->getPublic(true, 'hex');
        if (! is_string($publicKeyHex)) {
            throw ValidationException::withMessages([
                'secret' => 'Bitcoin secret could not be validated.',
            ]);
        }

        $derived = $this->bitcoinAddresses->p2wpkhAddressFromPublicKey($publicKeyHex);
        if (! is_string($derived) || $derived !== $address) {
            throw ValidationException::withMessages([
                'address' => 'Address does not match the imported Bitcoin secret.',
            ]);
        }

        return [
            'address' => $derived,
            'secret' => $privateKeyHex,
            'secret_format' => 'bitcoin_private_key_hex',
        ];
    }

    /**
     * @return array{address: string, secret: string, secret_format: string}
     */
    private function validateTon(string $address, string $secret, string $secretFormat): array
    {
        if ($secretFormat !== 'ton_secret_key_base64') {
            throw ValidationException::withMessages([
                'secret_format' => 'Unsupported TON secret format.',
            ]);
        }

        $secretKey = base64_decode($secret, true);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw ValidationException::withMessages([
                'secret' => 'TON import requires a 64-byte secret key.',
            ]);
        }

        $derived = $this->tonWalletAddresses->addressFromSecretKey($secretKey);
        $normalizedAddress = $this->tonAddresses->normalizeAddress($address);
        $normalizedDerived = $this->tonAddresses->normalizeAddress($derived);

        if (! is_string($normalizedAddress) || ! is_string($normalizedDerived) || $normalizedAddress !== $normalizedDerived) {
            throw ValidationException::withMessages([
                'address' => 'Address does not match the imported TON secret.',
            ]);
        }

        return [
            'address' => $normalizedAddress,
            'secret' => base64_encode($secretKey),
            'secret_format' => 'ton_secret_key_base64',
        ];
    }

    private function deriveEvmAddress(string $privateKeyHex): string
    {
        $ec = new EC('secp256k1');
        $publicKeyHex = $ec->keyFromPrivate($privateKeyHex)->getPublic(false, 'hex');
        if (! is_string($publicKeyHex) || ! str_starts_with($publicKeyHex, '04')) {
            throw ValidationException::withMessages([
                'secret' => 'EVM secret could not be validated.',
            ]);
        }

        $publicKeyBody = hex2bin(substr($publicKeyHex, 2));
        if ($publicKeyBody === false) {
            throw ValidationException::withMessages([
                'secret' => 'EVM secret could not be validated.',
            ]);
        }

        return strtolower('0x'.substr(Keccak::hash($publicKeyBody, 256), -40));
    }
}
