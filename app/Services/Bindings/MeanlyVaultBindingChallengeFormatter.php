<?php

namespace App\Services\Bindings;

use App\Contracts\BindingChallengeFormatter;
use App\Models\VaultIdentity;
use Illuminate\Support\Carbon;

class MeanlyVaultBindingChallengeFormatter implements BindingChallengeFormatter
{
    public function format(
        VaultIdentity $vault,
        string $bindingType,
        string $bindingKey,
        string $bindingValueNormalized,
        string $nonce,
        Carbon $expiresAt,
    ): string {
        return implode("\n", [
            'Meanly Vault Binding Challenge',
            'Vault ID: '.$vault->id,
            'Binding Type: '.$bindingType,
            'Binding Key: '.$bindingKey,
            'Address: '.$bindingValueNormalized,
            'Nonce: '.$nonce,
            'Expires At: '.$expiresAt->toIso8601String(),
        ]);
    }
}
