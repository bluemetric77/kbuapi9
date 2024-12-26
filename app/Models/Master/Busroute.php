<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Busroute extends Model
{
    protected $table = 'm_bus_route';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'est_distance' => 'float',
        'rate' => 'float',
        'target' => 'float',
        'breakpoint'=>'string',
        'target_min' => 'float',
        'target_min2' => 'float',
        'start_factor_go' => 'float',
        'start_factor_end' => 'float',
        'dest_factor_go' => 'float',
        'dest_factor_end' => 'float',
        'start_point_go' => 'float',
        'start_point_end' => 'float',
        'dest_point_go' => 'float',
        'dest_point_end' => 'float',
        'is_active'=>'string'
    ];
}
