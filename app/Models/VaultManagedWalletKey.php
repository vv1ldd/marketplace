<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultManagedWalletKey extends Model
{
    protected $fillable = [
        'vault_id',
        'identity_binding_id',
        'network_key',
        'address_normalized',
        'key_reference',
        'encrypted_secret',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function identityBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'identity_binding_id');
    }
}
