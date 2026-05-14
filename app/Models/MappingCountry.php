<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingCountry extends Model
{
    protected $table = 'mapping_countries';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name_ru',
        'name_en',
        'name_es',
        'name_tr',
        'name_tk',
        'accessibility_score',
        'regulatory_status',
        'has_capital_controls',
        'local_notes',
        'primary_currency_id',
    ];

    protected $casts = [
        'accessibility_score' => 'integer',
        'has_capital_controls' => 'boolean',
    ];

    public function primaryCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'primary_currency_id');
    }
}
