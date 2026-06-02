<?php

namespace FazerSdk\Endpoints;

class Catalog extends AbstractEndpoint
{
    public function getGames(): array
    {
        return $this->request('GET', 'games')->json('games') ?? [];
    }

    public function getTopupProducts(?string $gameId = null): array
    {
        return $this->request('GET', 'topup/products', $gameId ? ['game_id' => $gameId] : [])->json('products') ?? [];
    }

    public function getGiftCardProducts(?string $category = null): array
    {
        return $this->request('GET', 'giftcards/products', $category ? ['category' => $category] : [])->json('products') ?? [];
    }

    public function getGameKeys(): array
    {
        return $this->request('GET', 'gamekeys')->json('games') ?? [];
    }

    public function getGameKeyProducts(string $gameId): array
    {
        return $this->request('POST', 'gamekeys/products', ['game_id' => $gameId])->json('products') ?? [];
    }

    public function checkGameKeyRegion(string $gameId): array
    {
        return $this->request('POST', 'gamekeys/region-restriction', ['game_id' => $gameId])->json() ?? [];
    }

    public function getSteamGiftGames(?int $limit = null): array
    {
        return $this->request('GET', 'steamgifts/games', $limit ? ['limit' => $limit] : [])->json('games') ?? [];
    }

    public function getSteamGiftEditions(int $appid): array
    {
        return $this->request('GET', "steamgifts/games/{$appid}")->json('game') ?? [];
    }

    public function getTelegramPremium(): array
    {
        return $this->request('GET', 'telegram/premium')->json('items') ?? [];
    }

    public function getTelegramStars(): array
    {
        return $this->request('GET', 'telegram/stars')->json('items') ?? [];
    }

    public function getRobloxPacks(): array
    {
        return $this->request('GET', 'roblox/packages/products')->json('products') ?? [];
    }
}
