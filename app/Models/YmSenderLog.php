<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YmSenderLog extends Model
{
    protected $fillable = [
        'lang_region_id',
        'price_region_id',
        'send_id',
        'request',
        'status',
        'response',
    ];
}
