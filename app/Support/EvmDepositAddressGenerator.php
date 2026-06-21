<?php

namespace App\Support;

class EvmDepositAddressGenerator
{
    public function generate(string $networkKey, int $legalEntityId, int $intentId): string
    {
        $seed = hash('sha256', implode('|', [
            'meanly-evm-deposit',
            $networkKey,
            $legalEntityId,
            $intentId,
            (string) config('app.key'),
        ]));

        return '0x'.substr($seed, 0, 40);
    }
}
