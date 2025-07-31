<?php

namespace App\Models\PlayStation;

use App\Models\Order\OrderItems;
use Illuminate\Database\Eloquent\Model;

class PlayStationTypeForm extends Model
{
    protected $fillable = [
        'name',
    ];

    public $timestamps = false;

    public function play_station_alts()
    {
        return $this->hasMany(PlayStationAlt::class, 'type_form_id', 'id');
    }

    public function order_items()
    {
        return $this->hasMany(OrderItems::class, 'type_form_id', 'id');
    }
}
