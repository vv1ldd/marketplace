<?php

namespace App\Services;

use App\Models\SovereignLedger;
use App\Models\LegalEntity;
use Illuminate\Support\Facades\Log;

class L1StateService
{
    /**
     * Reconstruct token balances by replaying the Sovereign Ledger chain.
     *
     * RUBT is a 1:1 ledger-backed claim on deposited RUB. SL1 remains the
     * native usage/gas asset. Legacy balance keys are returned as projections.
     */
    public function reconstructBalance(LegalEntity $legalEntity): array
    {
        try {
            // 🛡️ Integrity check first using our built-in cryptographically chained validation
            $integrity = app(LedgerService::class)->verifyLegalEntityIntegrity($legalEntity);
            if (!$integrity['valid']) {
                Log::emergency("L1 LEDGER CORRUPTION DETECTED for Legal Entity #{$legalEntity->id}! Falling back to MySQL columns.", [
                    'errors' => $integrity['errors']
                ]);
                return $this->fallbackProjection($legalEntity);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to verify L1 ledger for Legal Entity #{$legalEntity->id}: " . $e->getMessage());
            return $this->fallbackProjection($legalEntity);
        }

        $entries = SovereignLedger::where('legal_entity_id', $legalEntity->id)
            ->orderBy('id', 'asc')
            ->get();

        $rubtAvailableBalance = 0.0;
        $rubtReservedBalance = 0.0;
        $sl1AvailableBalance = 1000.0000; // default test balance
        $sl1ReservedBalance = 0.0000;

        foreach ($entries as $entry) {
            $type = $entry->event_type;
            $payload = $entry->payload ?? [];
            $asset = $this->assetForPayload($payload);

            switch ($type) {
                case 'DEPOSIT_INTENT_CLEARED':
                case 'FINANCE_TOPUP':
                case 'FINANCE_DEPOSIT':
                case 'FINANCE_CREDIT_GRANTED':
                    if ($asset === 'SL1') {
                        $sl1AvailableBalance += $this->sl1Amount($payload);
                    } else {
                        $rubtAvailableBalance += $this->rubtAmount($payload);
                    }
                    break;

                case 'FINANCE_HOLD':
                    if ($asset === 'SL1') {
                        $amountSl1 = $this->sl1Amount($payload);
                        $gasSl1 = $this->gasFeeSl1($payload);
                        $sl1AvailableBalance -= ($amountSl1 + $gasSl1);
                        $sl1ReservedBalance += $amountSl1;
                    } else {
                        $amount = $this->rubtAmount($payload);
                        $rubtAvailableBalance -= $amount;
                        $rubtReservedBalance += $amount;
                    }
                    break;

                case 'STOCK_REPLENISH':
                case 'FINANCE_CAPTURE':
                case 'FINANCE_CAPTURE_MANUAL':
                    if ($asset === 'SL1') {
                        $amountSl1 = $this->sl1Amount($payload);
                        $gasSl1 = $this->gasFeeSl1($payload);
                        if ($sl1ReservedBalance >= $amountSl1) {
                            $sl1ReservedBalance -= $amountSl1;
                        } else {
                            $sl1AvailableBalance -= ($amountSl1 + $gasSl1);
                        }
                    } else {
                        $amount = $this->rubtAmount($payload);
                        if ($rubtReservedBalance >= $amount) {
                            $rubtReservedBalance -= $amount;
                        } else {
                            $rubtAvailableBalance -= $amount;
                        }
                    }
                    break;

                case 'FINANCE_RELEASE_HOLD':
                case 'FINANCE_RELEASE':
                    if ($asset === 'SL1') {
                        $amountSl1 = $this->sl1Amount($payload);
                        $gasSl1 = $this->gasFeeSl1($payload);
                        $sl1AvailableBalance += ($amountSl1 + $gasSl1);
                        $sl1ReservedBalance -= $amountSl1;
                    } else {
                        $amount = $this->rubtAmount($payload);
                        $rubtAvailableBalance += $amount;
                        $rubtReservedBalance -= $amount;
                    }
                    break;
            }
        }

        return $this->projection(
            $rubtAvailableBalance,
            $rubtReservedBalance,
            $sl1AvailableBalance,
            $sl1ReservedBalance,
            blocks_processed: $entries->count(),
            integrity_secured: true
        );
    }

    private function fallbackProjection(LegalEntity $legalEntity): array
    {
        $rubtAvailable = (float) ($legalEntity->available_balance ?? $legalEntity->balance ?? 0.0);
        $rubtReserved = (float) ($legalEntity->reserved_balance ?? 0.0);
        $sl1Available = (float) ($legalEntity->native_token_balance ?? 1000.0);
        $sl1Reserved = (float) ($legalEntity->native_token_reserved ?? 0.0);

        return $this->projection(
            $rubtAvailable,
            $rubtReserved,
            $sl1Available,
            $sl1Reserved,
            blocks_processed: 0,
            integrity_secured: false,
        );
    }

    private function projection(
        float $rubtAvailable,
        float $rubtReserved,
        float $sl1Available,
        float $sl1Reserved,
        int $blocks_processed,
        bool $integrity_secured,
    ): array {
        $rubtAvailable = round($rubtAvailable, 2);
        $rubtReserved = round($rubtReserved, 2);
        $sl1Available = round($sl1Available, 4);
        $sl1Reserved = round($sl1Reserved, 4);

        return [
            'rubt_available_balance' => $rubtAvailable,
            'rubt_reserved_balance' => $rubtReserved,
            'rubt_total_balance' => round($rubtAvailable + $rubtReserved, 2),
            'sl1_available_balance' => $sl1Available,
            'sl1_reserved_balance' => $sl1Reserved,
            'sl1_total_balance' => round($sl1Available + $sl1Reserved, 4),

            // Legacy projection keys used by existing UI and services.
            'available_balance' => $rubtAvailable,
            'reserved_balance' => $rubtReserved,
            'total_balance' => round($rubtAvailable + $rubtReserved, 2),
            'native_available_balance' => $sl1Available,
            'native_reserved_balance' => $sl1Reserved,
            'native_total_balance' => round($sl1Available + $sl1Reserved, 4),

            'blocks_processed' => $blocks_processed,
            'integrity_secured' => $integrity_secured,
        ];
    }

    private function assetForPayload(array $payload): string
    {
        $asset = strtoupper((string) ($payload['asset'] ?? $payload['currency'] ?? ''));

        if (in_array($asset, ['SL1', 'RUBT'], true)) {
            return $asset;
        }

        if (($payload['payment_method'] ?? null) === 'native_token') {
            return 'SL1';
        }

        return 'RUBT';
    }

    private function rubtAmount(array $payload): float
    {
        return (float) ($payload['token_amount'] ?? $payload['amount_rub'] ?? $payload['amount'] ?? 0);
    }

    private function sl1Amount(array $payload): float
    {
        return (float) ($payload['sl1_amount'] ?? $payload['token_amount'] ?? $payload['amount'] ?? 0);
    }

    private function gasFeeSl1(array $payload): float
    {
        return (float) ($payload['gas_fee'] ?? 0.0);
    }
}
