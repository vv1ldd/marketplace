<?php

namespace FazerSdk\Endpoints;

class Utilities extends AbstractEndpoint
{
    public function checkPlayerId(string $game, string $userId, ?string $serverId = null): array
    {
        return $this->request('GET', 'checkplayerid', array_filter([
            'game' => $game,
            'user_id' => $userId,
            'server_id' => $serverId,
        ], fn (mixed $value): bool => $value !== null))->json();
    }

    public function checkSteamLogin(string $username): bool
    {
        return (bool) $this->request('POST', 'steamtopup/check-login', ['username' => $username])->json('can_refill');
    }

    public function getSteamRates(?string $currency = null): array
    {
        return $this->request('GET', 'steamtopup/rates', $currency ? ['currency' => $currency] : [])->json('rates') ?? [];
    }
}
