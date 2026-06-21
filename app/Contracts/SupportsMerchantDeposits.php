<?php

namespace App\Contracts;

use App\Models\LegalEntity;

interface SupportsMerchantDeposits
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function merchantDepositPayload(LegalEntity $legalEntity, float $amountRub, array $options = []): array;

    /**
     * @param array<string, mixed> $proofPayload
     * @return array{valid: bool, error?: string, proof?: array<string, mixed>, verification?: string}
     */
    public function verifyDepositProof(array $proofPayload): array;
}
