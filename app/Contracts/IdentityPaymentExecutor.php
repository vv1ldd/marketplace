<?php

namespace App\Contracts;

interface IdentityPaymentExecutor
{
    /**
     * Broadcast a managed-wallet USDC transfer on an EVM rail.
     *
     * @return array{transaction_hash: string, network: string}
     */
    public function executeUsdcTransfer(
        int $senderBindingId,
        string $recipientAddressNormalized,
        string $amountWei,
        string $networkKey,
    ): array;
}
