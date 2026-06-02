<?php

namespace FazerSdk\Endpoints;

class Orders extends AbstractEndpoint
{
    public function getAll(): array
    {
        return $this->request('GET', 'orders')->json('orders') ?? [];
    }

    public function getStatus(string $orderId): array
    {
        return $this->request('GET', "orders/{$orderId}")->json() ?? [];
    }

    public function createGiftCardOrder(string $productId, int $quantity = 1): array
    {
        return $this->request('POST', 'giftcards/order', [
            'product_id' => $productId,
            'quantity' => $quantity,
        ])->json();
    }

    public function createTopupOrder(string $productId, int $quantity, array $gameFields): array
    {
        return $this->request('POST', 'topup/order', [
            'product_id' => $productId,
            'quantity' => $quantity,
            'game_fields' => $gameFields,
        ])->json();
    }

    public function createGameKeyOrder(string $productId, int $quantity = 1): array
    {
        return $this->request('POST', 'gamekeys/order', [
            'product_id' => $productId,
            'quantity' => $quantity,
        ])->json();
    }

    public function createSteamTopupOrder(string $username, float $amountUsd): array
    {
        return $this->request('POST', 'steamtopup/order', [
            'username' => $username,
            'amount_usd' => $amountUsd,
        ])->json();
    }

    public function createSteamGiftOrder(int $appid, int $packageId, string $region, string $inviteUrl): array
    {
        return $this->request('POST', 'steamgifts/order', [
            'appid' => $appid,
            'package_id' => $packageId,
            'region' => $region,
            'invite_url' => $inviteUrl,
        ])->json();
    }

    public function createTelegramPremiumOrder(string $username, int $months): array
    {
        return $this->request('POST', 'telegram/premium/gift', [
            'username' => $username,
            'months' => $months,
        ])->json();
    }

    public function createTelegramStarsOrder(string $username, int $amount): array
    {
        return $this->request('POST', 'telegram/stars/buy', [
            'username' => $username,
            'amount' => $amount,
        ])->json();
    }

    public function createRobloxPackOrder(string $productId, bool $isBackup, string $login, string $password, array $backupCodes = []): array
    {
        $payload = [
            'product_id' => $productId,
            'is_backup' => $isBackup,
            'login' => $login,
            'password' => $password,
        ];

        if ($isBackup) {
            $payload['backup_codes'] = $backupCodes;
        }

        return $this->request('POST', 'roblox/packages/buy', $payload)->json();
    }

    public function getRobloxChat(string $chatId): array
    {
        return $this->request('GET', "roblox/packages/chat/{$chatId}")->json('messages') ?? [];
    }

    public function sendRobloxChatMessage(string $chatId, string $content): array
    {
        return $this->request('POST', "roblox/packages/chat/{$chatId}/send", ['content' => $content])->json();
    }
}
