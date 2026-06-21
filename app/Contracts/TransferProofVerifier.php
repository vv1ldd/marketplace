<?php

namespace App\Contracts;

interface TransferProofVerifier
{
    /**
     * @param array{
     *     binding_key: string,
     *     transaction_hash: string,
     *     token_contract: string,
     *     chain_id: int,
     *     expected_recipient: string,
     *     minimum_amount: string,
     *     expected_sender?: string|null
     * } $criteria
     * @return array{
     *     valid: bool,
     *     error?: string,
     *     error_code?: string,
     *     proof?: array<string, mixed>
     * }
     */
    public function verify(array $criteria): array;
}
