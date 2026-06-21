<?php

namespace App\Support;

use Illuminate\Support\Str;

class EvmErc20BalanceReader
{
    private const BALANCE_OF_SELECTOR = '0x70a08231';

    public function __construct(
        private readonly EvmRpcClient $rpcClient,
    ) {}

    public function balanceOf(string $rpcUrl, string $tokenContract, string $walletAddress): ?string
    {
        $walletAddress = Str::lower(trim($walletAddress));
        $tokenContract = Str::lower(trim($tokenContract));

        if (! preg_match('/^0x[a-f0-9]{40}$/', $walletAddress)) {
            return null;
        }

        if (! preg_match('/^0x[a-f0-9]{40}$/', $tokenContract)) {
            return null;
        }

        $paddedAddress = str_pad(substr($walletAddress, 2), 64, '0', STR_PAD_LEFT);
        $data = self::BALANCE_OF_SELECTOR.$paddedAddress;

        return $this->rpcClient->ethCall($rpcUrl, $tokenContract, $data);
    }

    public function formatAmount(string $rawHex, int $decimals): string
    {
        $decimal = $this->hexToDecimal($rawHex);
        if ($decimals === 0) {
            return $decimal;
        }

        return bcdiv($decimal, bcpow('10', (string) $decimals, 0), $decimals);
    }

    public function displayAmount(string $amount, string $symbol, int $decimals): string
    {
        $formatted = rtrim(rtrim(number_format((float) $amount, min(6, $decimals), '.', ''), '0'), '.');

        return $formatted.' '.strtoupper($symbol);
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = Str::lower(ltrim(trim($hex), '0x'));
        if ($hex === '') {
            return '0';
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        return (string) hexdec($hex);
    }
}
