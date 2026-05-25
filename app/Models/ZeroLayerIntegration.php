<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZeroLayerIntegration extends Model
{
    protected $guarded = [];

    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function signals(): HasMany
    {
        return $this->hasMany(ZeroLayerSignal::class);
    }
}
