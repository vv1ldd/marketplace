<?php

namespace App\Support;

use Illuminate\Support\Str;

class BindingValueCanonicalizer
{
    /**
     * @return array{original: string, normalized: string}
     */
    public function canonicalize(string $protocol, string $value): array
    {
        $original = trim($value);

        if ($protocol === 'evm') {
            if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $original)) {
                return [
                    'original' => $original,
                    'normalized' => $original,
                ];
            }

            return [
                'original' => $original,
                'normalized' => Str::lower($original),
            ];
        }

        if ($protocol === 'utxo') {
            if (preg_match('/^bc1[a-z0-9]+$/i', $original)) {
                return [
                    'original' => $original,
                    'normalized' => Str::lower($original),
                ];
            }

            if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $original)) {
                return [
                    'original' => $original,
                    'normalized' => $original,
                ];
            }

            return [
                'original' => $original,
                'normalized' => $original,
            ];
        }

        if ($protocol === 'solana') {
            return [
                'original' => $original,
                'normalized' => $original,
            ];
        }

        return [
            'original' => $original,
            'normalized' => $original,
        ];
    }
}
