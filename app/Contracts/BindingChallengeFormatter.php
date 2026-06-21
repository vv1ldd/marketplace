<?php

namespace App\Contracts;

use App\Models\VaultIdentity;
use Illuminate\Support\Carbon;

interface BindingChallengeFormatter
{
    public function format(
        VaultIdentity $vault,
        string $bindingType,
        string $bindingKey,
        string $bindingValueNormalized,
        string $nonce,
        Carbon $expiresAt,
    ): string;
}
