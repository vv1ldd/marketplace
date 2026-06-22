<?php

namespace Tests\Unit;

use App\Support\TonSignDataVerifier;
use Tests\TestCase;

class TonSignDataVerifierTest extends TestCase
{
    public function test_verify_text_sign_data_accepts_valid_tonconnect_signature(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for TON signData tests.');
        }

        config([
            'blockchain_networks.ton_connect.allowed_domains' => 'meanly.test',
        ]);

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKeyHex = bin2hex(sodium_crypto_sign_publickey($keypair));
        $accountHash = hex2bin(str_repeat('ab', 32));
        $addressRaw = '0:'.bin2hex($accountHash);
        $message = "Meanly TON ownership\nNonce: test-nonce";
        $domain = 'meanly.test';
        $timestamp = time();

        $signingMessage = "\xFF\xFF"
            .'ton-connect/sign-data/'
            .pack('N', 0)
            .$accountHash
            .pack('N', strlen($domain))
            .$domain
            .pack('J', $timestamp)
            .'txt'
            .pack('N', strlen($message))
            .$message;

        $messageHash = hash('sha256', $signingMessage, true);
        $signature = base64_encode(sodium_crypto_sign_detached($messageHash, $secretKey));

        $verifier = app(TonSignDataVerifier::class);

        $this->assertTrue($verifier->verifyTextSignData(
            signatureBase64: $signature,
            addressRaw: $addressRaw,
            timestamp: $timestamp,
            domain: $domain,
            payload: ['type' => 'text', 'text' => $message],
            walletPublicKey: $publicKeyHex,
            expectedMessage: $message,
        ));
    }

    public function test_verify_text_sign_data_rejects_payload_mismatch(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for TON signData tests.');
        }

        config([
            'blockchain_networks.ton_connect.allowed_domains' => 'meanly.test',
        ]);

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKeyHex = bin2hex(sodium_crypto_sign_publickey($keypair));
        $accountHash = hex2bin(str_repeat('cd', 32));
        $addressRaw = '0:'.bin2hex($accountHash);
        $message = 'signed message';
        $domain = 'meanly.test';
        $timestamp = time();

        $signingMessage = "\xFF\xFF"
            .'ton-connect/sign-data/'
            .pack('N', 0)
            .$accountHash
            .pack('N', strlen($domain))
            .$domain
            .pack('J', $timestamp)
            .'txt'
            .pack('N', strlen($message))
            .$message;

        $signature = base64_encode(sodium_crypto_sign_detached(
            hash('sha256', $signingMessage, true),
            $secretKey,
        ));

        $verifier = app(TonSignDataVerifier::class);

        $this->assertFalse($verifier->verifyTextSignData(
            signatureBase64: $signature,
            addressRaw: $addressRaw,
            timestamp: $timestamp,
            domain: $domain,
            payload: ['type' => 'text', 'text' => 'different message'],
            walletPublicKey: $publicKeyHex,
            expectedMessage: $message,
        ));
    }
}
