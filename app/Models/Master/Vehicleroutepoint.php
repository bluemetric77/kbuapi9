<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Vehicleroutepoint extends Model
{
    protected $table = 'm_vehicle_routepoint';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded=[];
    protected $casts = [
        'breakpoint' => 'string',
        'point'=>'float',
        'target'=>'float',
        'target_min'=>'float',
        'cost'=>'float',
        'dest_factor_end'=>'float',
        'dest_factor_go'=>'float',
        'dest_point_end'=>'float',
        'dest_point_go'=>'float',
        'start_factor_end'=>'float',
        'start_factor_go'=>'float',
        'start_point_end'=>'float',
        'start_point_go'=>'float',
        'target_min2'=>'float',
        'is_active'=>'string',
        'breakpoint'=>'string',
        'target_min2'=>'float'
    ];
}
