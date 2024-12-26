<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    protected $table = 'm_checkpoint';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts =[
        'factor_point'=>'float',
        'point'=>'float',
        'is_active'=>'string',
        'breakpoint'=>'string',
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
    ];
}
