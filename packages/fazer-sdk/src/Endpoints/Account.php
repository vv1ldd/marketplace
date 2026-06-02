<?php

namespace FazerSdk\Endpoints;

class Account extends AbstractEndpoint
{
    public function getMe(): array
    {
        return $this->request('GET', 'me')->json();
    }

    public function getBalance(): array
    {
        return $this->request('GET', 'balance')->json();
    }

    public function getTransactions(): array
    {
        return $this->request('GET', 'transactions')->json('transactions') ?? [];
    }

    public function getTransaction(string $id): array
    {
        return $this->request('GET', "transaction/{$id}")->json();
    }

    public function getTopupMethods(): array
    {
        return $this->request('GET', 'balance/topup/methods')->json('methods') ?? [];
    }

    public function getSubscription(): array
    {
        return $this->request('GET', 'subscription')->json('subscription') ?? [];
    }

    public function renewSubscription(string $plan, int $months, bool $autoRenew = true): array
    {
        return $this->request('POST', 'subscription/renew', [
            'plan' => $plan,
            'months' => $months,
            'auto_renew' => $autoRenew,
        ])->json();
    }

    public function upgradeSubscription(string $plan, int $months, bool $autoRenew = false): array
    {
        return $this->request('POST', 'subscription/upgrade', [
            'plan' => $plan,
            'months' => $months,
            'auto_renew' => $autoRenew,
        ])->json();
    }

    public function setAutoRenew(bool $enabled): array
    {
        return $this->request('POST', 'subscription/auto-renew', ['enabled' => $enabled])->json();
    }

    public function createTopupRequest(float $amount, string $methodId): array
    {
        return $this->request('POST', 'balance/topup', [
            'amount' => $amount,
            'method_id' => $methodId,
        ])->json();
    }

    public function verifyBinancePay(string $transactionId, string $binanceOrderId, string $orderId): array
    {
        return $this->request('POST', 'balance/topup/verify-binance-pay', [
            'transaction_id' => $transactionId,
            'binance_order_id' => $binanceOrderId,
            'order_id' => $orderId,
        ])->json();
    }
}
