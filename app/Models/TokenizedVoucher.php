<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\VaultEncrypted;

class TokenizedVoucher extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Sovereign Vault Engine:
     * We keep the voucher key encrypted at rest in HashiCorp Vault.
     * The blockchain only knows the Token ID, keeping the asset perfectly secure.
     */
    protected $casts = [
        'encrypted_key' => VaultEncrypted::class,
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
