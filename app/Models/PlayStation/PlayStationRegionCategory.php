<?php

namespace App\Models\PlayStation;

use Illuminate\Database\Eloquent\Model;

class PlayStationRegionCategory extends Model
{
    protected $fillable = ['region_id', 'category_id', 'count', 'total_count'];
}
