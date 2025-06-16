<?php

namespace App\Models\PlayStation;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlayStationRegion extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug'];

    public $timestamps = false;
}
