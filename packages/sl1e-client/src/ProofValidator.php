<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Exception\Sl1eValidationException;

final class ProofValidator
{
    /**
     * @param array<string, mixed> $proofResponse
     */
    public function validate(array $proofResponse, VerificationContext $context, string $proofToken = ''): IdentityProof
    {
        $proofToken = trim($proofToken !== '' ? $proofToken : (string) ($proofResponse['proof_token'] ?? ''));
        $proof = $proofResponse['proof'] ?? [];

        if (($proofResponse['active'] ?? false) !== true || ! is_array($proof)) {
            throw new Sl1eValidationException('SL1E proof is not active.');
        }

        $type = (string) ($proof['type'] ?? '');
        if (! in_array($type, ['sl1e.login.proof.v1', 'sl1e.register.proof.v1', 'sl1e.intent.proof.v1'], true)) {
            throw new Sl1eValidationException('SL1E proof type mismatch.');
        }

        $this->assertEquals($context->clientId, (string) ($proof['clientId'] ?? ''), 'SL1E client mismatch.');
        $this->assertEquals($context->redirectUri, (string) ($proof['redirectUri'] ?? ''), 'SL1E redirect URI mismatch.');
        $this->assertEquals($context->state, (string) ($proof['state'] ?? ''), 'SL1E state mismatch.');
        $this->assertEquals($context->nonce, (string) ($proof['nonce'] ?? ''), 'SL1E nonce mismatch.');
        $this->assertEquals($context->mode, (string) ($proof['mode'] ?? $context->mode), 'SL1E mode mismatch.');

        $entityAddress = strtolower((string) ($proof['entityAddress'] ?? $proofResponse['identity']['entity_l1_address'] ?? ''));
        if (preg_match('/^sl1e_[a-f0-9]{39}$/i', $entityAddress) !== 1) {
            throw new Sl1eValidationException('SL1E entity address is malformed.');
        }

        $keyAddress = $proof['keyAddress'] ?? $proofResponse['identity']['key_l1_address'] ?? null;
        if (is_string($keyAddress)) {
            $keyAddress = strtolower($keyAddress);
            if (preg_match('/^sl1_[a-f0-9]{40}$/i', $keyAddress) !== 1) {
                throw new Sl1eValidationException('SL1E key address is malformed.');
            }
        } else {
            $keyAddress = null;
        }

        $this->assertFreshness($proof, $context);

        if ($proofToken === '') {
            throw new Sl1eValidationException('SL1E proof token is missing.');
        }

        return new IdentityProof(
            entityAddress: $entityAddress,
            keyAddress: $keyAddress,
            proofToken: $proofToken,
            proofTokenHash: hash('sha256', $proofToken),
            mode: (string) ($proof['mode'] ?? $context->mode),
            proof: $proof,
        );
    }

    private function assertEquals(string $expected, string $actual, string $message): void
    {
        if ($expected === '' || $actual === '' || ! hash_equals($expected, $actual)) {
            throw new Sl1eValidationException($message);
        }
    }

    /**
     * @param array<string, mixed> $proof
     */
    private function assertFreshness(array $proof, VerificationContext $context): void
    {
        $expiresAt = isset($proof['expiresAt']) ? strtotime((string) $proof['expiresAt']) : false;
        if ($expiresAt === false || $expiresAt <= time()) {
            throw new Sl1eValidationException('SL1E proof expired.');
        }

        if (isset($proof['issuedAt'])) {
            $issuedAt = strtotime((string) $proof['issuedAt']);
            if ($issuedAt !== false && $issuedAt > time() + $context->clockSkewSeconds) {
                throw new Sl1eValidationException('SL1E proof was issued in the future.');
            }
        }
    }
}
