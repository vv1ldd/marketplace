<?php

namespace App\Services;

use App\Models\SovereignLedger;
use App\Models\LegalEntity;
use App\Services\LedgerService;
use App\Services\L1StateService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class L1ClearingService
{
    protected LedgerService $ledger;
    protected L1StateService $l1State;

    public function __construct(LedgerService $ledger, L1StateService $l1State)
    {
        $this->ledger = $ledger;
        $this->l1State = $l1State;
    }

    /**
     * Step 1: Partner submits a purchase request directly onto the L1 Ledger.
     * This places a cryptographically signed FINANCE_HOLD block.
     */
    public function dispatchOrderRequest(
        LegalEntity $partner,
        string $sku,
        int $quantity,
        float $costRub,
        string $referenceCode,
        string $paymentMethod = 'rub_token',
        ?string $assertionId = null,
        float $gasFee = 0.0,
        float $sl1Amount = 0.0,
        ?array $simpleLayerOneProof = null
    ): SovereignLedger {
        // 1. Ensure partner has sufficient available L1 balance
        $balances = $this->l1State->reconstructBalance($partner);
        
        if ($paymentMethod === 'native_token') {
            $totalRequiredSl1 = $sl1Amount + $gasFee;
            $availableSl1 = $balances['sl1_available_balance'] ?? $balances['native_available_balance'] ?? 0.0;
            if ($availableSl1 < $totalRequiredSl1) {
                throw new \Exception("Insufficient L1 native token balance. Required: " . number_format($totalRequiredSl1, 4) . " SL1, Available: " . number_format($availableSl1, 4) . " SL1.");
            }
        } else {
            $availableRubt = $balances['rubt_available_balance'] ?? $balances['available_balance'] ?? 0.0;
            if ($availableRubt < $costRub) {
                throw new \Exception("Insufficient L1 available balance. Required: {$costRub} RUBT, Available: {$availableRubt} RUBT.");
            }
        }

        // 2. Append FINANCE_HOLD block to L1 Ledger
        $payload = [
            'asset' => $paymentMethod === 'native_token' ? 'SL1' : 'RUBT',
            'amount_rub' => $costRub,
            'token_amount' => $paymentMethod === 'native_token' ? $sl1Amount : $costRub,
            'service_sku' => $sku,
            'quantity' => $quantity,
            'reference_code' => $referenceCode,
            'details' => "L1 Block Procurement for {$quantity}x {$sku}",
            'payment_method' => $paymentMethod,
            'assertion_id' => $assertionId,
            'simple_layer_one' => $simpleLayerOneProof,
            'tx_hash' => $simpleLayerOneProof['tx_hash'] ?? null,
            'tx_nonce' => $simpleLayerOneProof['tx_nonce'] ?? null,
            'gas_fee' => $gasFee,
            'sl1_amount' => $sl1Amount,
        ];

        return $this->ledger->record(
            null, // global/partner-level
            'FINANCE_HOLD',
            $partner,
            $payload,
            $partner,
            "DID:L1_PARTNER | #{$partner->id}"
        );
    }

    /**
     * Step 2: The Aggregator Sovereign Validator listens to the L1 Ledger and processes pending clearances.
     * It claims the codes from the warehouse and publishes a STOCK_REPLENISH block on L1!
     * 
     * IMPORTANT: In accordance with blockchain immutability principles, this method NEVER mutates
     * pre-existing ledger blocks. It determines processed queue items statelessly by reading the ledger stream.
     */
    public function processClearingQueue(): array
    {
        // 1. Find all FINANCE_HOLD holds
        $entries = SovereignLedger::where('event_type', 'FINANCE_HOLD')->get();

        $processed = 0;
        $failed = 0;
        $results = [];
        
        $vault = app(\App\Services\VaultTransitService::class);

        foreach ($entries as $entry) {
            $payload = $entry->payload;
            $refCode = $payload['reference_code'] ?? null;
            if (!$refCode) {
                continue;
            }

            // Statelessly verify if this hold has already been resolved in the ledger chain
            $alreadyResolved = SovereignLedger::whereIn('event_type', ['STOCK_REPLENISH', 'FINANCE_RELEASE_HOLD'])
                ->where('payload->reference_code', $refCode)
                ->exists();

            if ($alreadyResolved) {
                continue; // Skip already resolved requests
            }

            $partner = LegalEntity::find($entry->entity_id);
            if (!$partner) {
                continue;
            }

            $sku = $payload['service_sku'];
            $quantity = (int)$payload['quantity'];
            $amountRub = (float)$payload['amount_rub'];

            try {
                // We query our new secure LocalVoucher database pool directly using a locked transaction.
                $conn = DB::getDriverName() === 'sqlite' ? DB::getDefaultConnection() : 'mysql';
                $vouchers = DB::connection($conn)
                    ->transaction(function () use ($sku, $quantity, $refCode, $vault) {
                        
                        // Check availability first in the api_wildflow_dev schema
                        $availableCount = DB::table('api_wildflow_dev.local_vouchers')
                            ->where('service_sku', $sku)
                            ->where('is_used', false)
                            ->count();

                        if ($availableCount < $quantity) {
                            throw new \Exception("OUT_OF_STOCK");
                        }

                        // Retrieve and lock rows at DB level
                        $rows = DB::table('api_wildflow_dev.local_vouchers')
                            ->where('service_sku', $sku)
                            ->where('is_used', false)
                            ->lockForUpdate()
                            ->limit($quantity)
                            ->get();

                        $cards = [];
                        foreach ($rows as $row) {
                            // Decrypt the code PIN securely from AES-256 vault
                            $decryptedCode = $vault->decrypt($row->code);
                            
                            // Re-encrypt/secure the voucher code payload on-chain
                            $blockchainSecuredCode = $vault->encrypt($decryptedCode);

                            DB::table('api_wildflow_dev.local_vouchers')
                                ->where('id', $row->id)
                                ->update([
                                    'is_used' => true,
                                    'order_id' => crc32($refCode), // numerical order identifier
                                    'claimed_at' => now()
                                ]);

                            $cards[] = [
                                'code' => $blockchainSecuredCode, // Cryptographically secured on-chain payload
                                'serial' => $row->serial,
                                'expiry' => $row->expiry_date
                            ];
                        }

                        return $cards;
                    });

                // 3. Procurement succeeded! Append STOCK_REPLENISH block on L1 Ledger
                $replenishPayload = [
                    'amount_rub' => $amountRub,
                    'asset' => ($payload['payment_method'] ?? 'rub_token') === 'native_token' ? 'SL1' : 'RUBT',
                    'token_amount' => ($payload['payment_method'] ?? 'rub_token') === 'native_token'
                        ? (float)($payload['sl1_amount'] ?? 0.0)
                        : $amountRub,
                    'service_sku' => $sku,
                    'quantity' => $quantity,
                    'reference_code' => $refCode,
                    'order_status' => 'COMPLETED',
                    'vouchers' => $vouchers, // Secure Delivery inside L1 block payload!
                    'details' => "Procured {$quantity}x {$sku} successfully via L1 block clearing.",
                    'payment_method' => $payload['payment_method'] ?? 'rub_token',
                    'assertion_id' => $payload['assertion_id'] ?? null,
                    'simple_layer_one' => $payload['simple_layer_one'] ?? null,
                    'tx_hash' => $payload['tx_hash'] ?? data_get($payload, 'simple_layer_one.tx_hash'),
                    'tx_nonce' => $payload['tx_nonce'] ?? data_get($payload, 'simple_layer_one.tx_nonce'),
                    'gas_fee' => (float)($payload['gas_fee'] ?? 0.0),
                    'sl1_amount' => (float)($payload['sl1_amount'] ?? 0.0),
                ];

                $this->ledger->record(
                    null,
                    'STOCK_REPLENISH',
                    $partner,
                    $replenishPayload,
                    $partner,
                    "DID:L1_VALIDATOR | #NODE_01"
                );

                $processed++;
                $results[] = [
                    'reference_code' => $refCode,
                    'status' => 'COMPLETED',
                    'vouchers_count' => count($vouchers)
                ];

            } catch (\Throwable $e) {
                // 4. Procurement failed! Append FINANCE_RELEASE_HOLD block on L1 Ledger to refund
                $releasePayload = [
                    'amount_rub' => $amountRub,
                    'asset' => ($payload['payment_method'] ?? 'rub_token') === 'native_token' ? 'SL1' : 'RUBT',
                    'token_amount' => ($payload['payment_method'] ?? 'rub_token') === 'native_token'
                        ? (float)($payload['sl1_amount'] ?? 0.0)
                        : $amountRub,
                    'service_sku' => $sku,
                    'quantity' => $quantity,
                    'reference_code' => $refCode,
                    'order_status' => 'FAILED',
                    'reason' => $e->getMessage() === 'OUT_OF_STOCK' ? 'OUT_OF_STOCK' : 'DISPATCH_ERROR',
                    'details' => "Procurement failed: " . $e->getMessage(),
                    'payment_method' => $payload['payment_method'] ?? 'rub_token',
                    'assertion_id' => $payload['assertion_id'] ?? null,
                    'simple_layer_one' => $payload['simple_layer_one'] ?? null,
                    'tx_hash' => $payload['tx_hash'] ?? data_get($payload, 'simple_layer_one.tx_hash'),
                    'tx_nonce' => $payload['tx_nonce'] ?? data_get($payload, 'simple_layer_one.tx_nonce'),
                    'gas_fee' => (float)($payload['gas_fee'] ?? 0.0),
                    'sl1_amount' => (float)($payload['sl1_amount'] ?? 0.0),
                ];

                $this->ledger->record(
                    null,
                    'FINANCE_RELEASE_HOLD',
                    $partner,
                    $releasePayload,
                    $partner,
                    "DID:L1_VALIDATOR | #NODE_01"
                );

                $failed++;
                $results[] = [
                    'reference_code' => $refCode,
                    'status' => 'FAILED',
                    'reason' => $e->getMessage()
                ];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results
        ];
    }
}
